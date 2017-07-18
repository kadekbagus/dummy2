<?php namespace Orbit\Queue\Elasticsearch;
/**
 * Update Elasticsearch index when news has been updated.
 *
 * @author kadek <kadek@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use DB;
use News;
use Advert;
use AdvertLocation;
use NewsMerchant;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Log;
use Orbit\FakeJob;
use Carbon\Carbon as Carbon;

class ESAdvertNewsUpdateQueue
{
    /**
     * Poster. The object which post the data to external system.
     *
     * @var poster.
     */
    protected $poster = NULL;

    /**
     * Class constructor.
     *
     * @param string $poster Object used to post the data.
     * @return void
     */
    public function __construct($poster = 'default')
    {
        if ($poster === 'default') {
            $this->poster = ESBuilder::create()
                                     ->setHosts(Config::get('orbit.elasticsearch.hosts'))
                                     ->build();
        } else {
            $this->poster = $poster;
        }
    }

    /**
     * Laravel main method to fire a job on a queue.
     */
    public function fire($job, $data)
    {
        $prefix = DB::getTablePrefix();
        $esConfig = Config::get('orbit.elasticsearch');
        $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
        $newsId = $data['news_id'];

        //Get now time
        $timezone = 'Asia/Jakarta'; // now with jakarta timezone
        $timestamp = date("Y-m-d H:i:s");
        $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
        $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();

        $advertData = Advert::select('adverts.advert_id', 'advert_placements.placement_type', 'advert_placements.placement_order', 'adverts.start_date', 'adverts.end_date', 'adverts.status', 'adverts.is_all_location')
                            ->join('advert_link_types', 'adverts.advert_link_type_id', '=', 'advert_link_types.advert_link_type_id')
                            ->join('advert_placements', 'advert_placements.advert_placement_id', '=', 'adverts.advert_placement_id')
                            ->whereIn('advert_placements.placement_type', ['preferred_list_regular', 'preferred_list_large', 'featured_list'])
                            ->where('advert_link_types.advert_type', 'news')
                            ->where('adverts.end_date', '>=', date("Y-m-d", strtotime($dateTime)))
                            ->where('adverts.link_object_id', $newsId)
                            ->groupBy('adverts.advert_id')
                            ->orderBy('adverts.advert_id')
                            ->get();

        if ($advertData->isEmpty()) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Advert for news ID %s is not found.', $job->getJobId(), $newsId)
            ];
        }

        $news = News::with(
                            'translations.media_orig',
                            'campaignLocations.categories',
                            'esCampaignLocations.geofence',
                            'campaignObjectPartners',
                            'adverts.media_orig',
                            'total_page_views'
                        )
                    ->with (['keywords' => function ($q) {
                                $q->groupBy('keyword');
                            }])
                    ->select(DB::raw("
                        {$prefix}news.*,
                        {$prefix}campaign_account.mobile_default_language,
                        {$prefix}users.user_id,
                        {$prefix}users.user_email,
                        CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                        THEN {$prefix}campaign_status.campaign_status_name
                        ELSE (CASE WHEN {$prefix}news.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                FROM {$prefix}news_merchant onm
                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                    LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                WHERE onm.news_id = {$prefix}news.news_id)
                        THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status
                    "))
                    ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                    ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                    ->join('users', 'users.user_id', '=', 'news.created_by')
                    ->where('news.news_id', $newsId)
                    ->where('news.object_type', 'news')
                    ->orderBy('news.news_id', 'asc')
                    ->first();

        if (! is_object($news)) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] News ID %s is not found.', $job->getJobId(), $newsId)
            ];
        }

        try {
            // 1 advert 1 document (advert_news)
            foreach ($advertData as $adverts) {
                // check exist elasticsearch index
                $params_search = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.advert_news.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.advert_news.type'),
                    'body' => [
                        'query' => [
                            'match' => [
                                '_id' => $adverts->advert_id
                            ]
                        ]
                    ]
                ];

                $response_search = $this->poster->search($params_search);

                $response = NULL;
                $params = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.advert_news.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.advert_news.type'),
                    'id' => $adverts->advert_id,
                    'body' => []
                ];

                $featuredGtmScore = 0;
                $featuredMallScore = 0;
                $preferredGtmScore = 0;
                $preferredMallScore = 0;

                $featuredGtmType = '';
                $featuredMallType = '';
                $preferredGtmType = '';
                $preferredMallType = '';

                //advert location
                if ($adverts->is_all_location === 'Y') {
                    $advertLocation = NewsMerchant::select(DB::raw("IF({$prefix}merchants.object_type = 'tenant', pm.merchant_id, {$prefix}merchants.merchant_id) as location_id"))
                                                ->leftjoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                                ->leftjoin('merchants as pm', DB::raw("pm.merchant_id"), '=', DB::raw("IF(isnull({$prefix}merchants.parent_id), {$prefix}merchants.merchant_id, {$prefix}merchants.parent_id) "))
                                                ->where('news_id', $news->news_id)
                                                ->union(DB::table()->selectRaw("0"))
                                                ->groupBy('location_id')
                                                ->get();
                } else {
                    $advertLocation = AdvertLocation::select('location_id')
                                                ->where('advert_id', $adverts->advert_id)
                                                ->get();
                }

                $advertLocationIds = array();
                foreach ($advertLocation as $location) {
                    if ($location->location_id === '0') {
                        // gtm
                        if ($adverts->placement_type === 'featured_list') {
                            if ($adverts->placement_order > $featuredGtmScore) {
                                $featuredGtmScore = $adverts->placement_order;
                                $featuredGtmType = $adverts->placement_type;
                            }
                        } else {
                            if ($adverts->placement_order > $preferredGtmScore) {
                                $preferredGtmScore = $adverts->placement_order;
                                $preferredGtmType = $adverts->placement_type;
                            }
                        }
                    } else {
                        // mall
                        if ($adverts->placement_type === 'featured_list') {
                            if ($adverts->placement_order > $featuredMallScore) {
                                $featuredMallScore = $adverts->placement_order;
                                $featuredMallType = $adverts->placement_type;
                            }
                        } else {
                            if ($adverts->placement_order > $preferredMallScore) {
                                $preferredMallScore = $adverts->placement_order;
                                $preferredMallType = $adverts->placement_type;
                            }
                        }
                    }

                    $advertLocationIds[] = $location->location_id;
                }

                $categoryIds = array();
                foreach ($news->campaignLocations as $campaignLocation) {
                    foreach ($campaignLocation->categories as $category) {
                        $categoryIds[] = $category->category_id;
                    }
                }

                $linkToTenants = array();
                foreach($news->esCampaignLocations as $esCampaignLocation) {
                    $linkToTenant = array(
                        "merchant_id" => $esCampaignLocation->merchant_id,
                        "parent_id"   => $esCampaignLocation->parent_id,
                        "name"        => $esCampaignLocation->name,
                        "mall_name"   => $esCampaignLocation->mall_name,
                        "object_type" => $esCampaignLocation->object_type,
                        "city"        => $esCampaignLocation->city,
                        "province"    => $esCampaignLocation->province,
                        "country"     => $esCampaignLocation->country,
                        "position"    => [
                            'lon' => $esCampaignLocation->geofence->longitude,
                            'lat' => $esCampaignLocation->geofence->latitude
                        ]
                    );

                    $linkToTenants[] = $linkToTenant;
                }

                $keywords = array();
                foreach ($news->keywords as $keyword) {
                    $keywords[] = $keyword->keyword;
                }

                $partnerIds = array();
                $partnerTokens = array();
                foreach ($news->campaignObjectPartners as $campaignObjectPartner) {
                    $partnerIds[] = $campaignObjectPartner->partner_id;
                    if (! empty($campaignObjectPartner->token)) {
                        $partnerTokens[] = $campaignObjectPartner->token;
                    }
                }

                $advertIds = array();
                foreach ($news->adverts as $advertCollection) {
                    $advertIds[] = $advertCollection->advert_id;
                }

                // get translation from default lang
                $defaultTranslation = array();
                foreach ($news->translations as $defTranslation) {
                    if ($defTranslation->name === $news->mobile_default_language) {
                        $defaultTranslation = array(
                            'name'          => $defTranslation->news_name,
                            'description'   => $defTranslation->description,
                            'language_id'   => $defTranslation->merchant_language_id,
                            'language_code' => $defTranslation->name,
                            'image_url'     => NULL
                        );
                    }
                }

                $translations = array();
                $translationBody['name_default'] = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(" ", "_", $defaultTranslation['name']));
                foreach ($news->translations as $translationCollection) {
                    $translation = array(
                        'name'          => $translationCollection->news_name,
                        'description'   => $translationCollection->description,
                        'language_id'   => $translationCollection->merchant_language_id,
                        'language_code' => $translationCollection->name,
                        'image_url'     => NULL
                    );

                    foreach ($translationCollection->media_orig as $media) {
                        $translation['image_url'] = $media->path;
                        $translation['image_cdn_url'] = $media->cdn_url;
                    }
                    $translations[] = $translation;

                    // for "sort A-Z" feature
                    $newsName = $translationCollection->news_name;
                    $newsDesc = $translationCollection->description;

                    //if name and description is empty fill with name and desc from default translation
                    if (empty($translationCollection->news_name) || $translationCollection->news_name === '') {
                        $newsName = $defaultTranslation['name'];
                    }

                    if (empty($translationCollection->description) || $translationCollection->description === '') {
                        $newsDesc = $defaultTranslation['description'];
                    }

                    $translationBody['name_' . $translationCollection->name] = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(" ", "_", $newsName));
                }

                $total_view_on_gtm = 0;
                $total_view_on_mall = array();
                foreach ($news->total_page_views as $key => $total_page_view) {
                    if ($total_page_view->location_id != '0') {
                        $total_views = array(
                            'total_views' => $total_page_view->total_view,
                            'location_id' => $total_page_view->location_id
                        );
                        $total_view_on_mall[] = $total_views;
                    } else {
                        $total_view_on_gtm = $total_page_view->total_view;
                    }
                }

                $body = [
                    'news_id'              => $news->news_id,
                    'name'                 => $news->news_name,
                    'description'          => $news->description,
                    'object_type'          => $news->object_type,
                    'begin_date'           => date('Y-m-d', strtotime($news->begin_date)) . 'T' . date('H:i:s', strtotime($news->begin_date)) . 'Z',
                    'end_date'             => date('Y-m-d', strtotime($news->end_date)) . 'T' . date('H:i:s', strtotime($news->end_date)) . 'Z',
                    'updated_at'           => date('Y-m-d', strtotime($news->updated_at)) . 'T' . date('H:i:s', strtotime($news->updated_at)) . 'Z',
                    'advert_start_date'    => date('Y-m-d', strtotime($adverts->start_date)) . 'T' . date('H:i:s', strtotime($adverts->start_date)) . 'Z',
                    'advert_end_date'      => date('Y-m-d', strtotime($adverts->end_date)) . 'T' . date('H:i:s', strtotime($adverts->end_date)) . 'Z',
                    'status'               => $news->status,
                    'advert_status'        => $adverts->status,
                    'campaign_status'      => $news->campaign_status,
                    'is_all_gender'        => $news->is_all_gender,
                    'is_all_age'           => $news->is_all_age,
                    'category_ids'         => $categoryIds,
                    'created_by'           => $news->user_id,
                    'creator_email'        => $news->user_email,
                    'default_lang'         => $news->mobile_default_language,
                    'translation'          => $translations,
                    'keywords'             => $keywords,
                    'partner_ids'          => $partnerIds,
                    'partner_tokens'       => $partnerTokens,
                    'advert_ids'           => $advertIds,
                    'link_to_tenant'       => $linkToTenants,
                    'is_exclusive'         => ! empty($news->is_exclusive) ? $news->is_exclusive : 'N',
                    'is_having_reward'     => $news->is_having_reward,
                    'gtm_page_views'       => $total_view_on_gtm,
                    'mall_page_views'      => $total_view_on_mall,
                    'featured_gtm_score'   => $featuredGtmScore,
                    'featured_mall_score'  => $featuredMallScore,
                    'preferred_gtm_score'  => $preferredGtmScore,
                    'preferred_mall_score' => $preferredMallScore,
                    'featured_gtm_type'    => $featuredGtmType,
                    'featured_mall_type'   => $featuredMallType,
                    'preferred_gtm_type'   => $preferredGtmType,
                    'preferred_mall_type'  => $preferredMallType,
                    'advert_location_ids'  => $advertLocationIds,
                    'advert_type'          => $adverts->placement_type
                ];

                $body = array_merge($body, $translationBody);

                if ($response_search['hits']['total'] > 0) {
                    $params = [
                        'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.advert_news.index'),
                        'type' => Config::get('orbit.elasticsearch.indices.advert_news.type'),
                        'id' => $response_search['hits']['hits'][0]['_id']
                    ];

                    $response = $this->poster->delete($params);
                }

                $params['body'] = $body;
                $response = $this->poster->index($params);

                // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
                ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);
            }

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s; News ID: %s; News Name: %s',
                                $job->getJobId(),
                                $esConfig['indices']['news']['index'],
                                $esConfig['indices']['news']['type'],
                                $news->news_id,
                                $news->news_name);
            Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['news']['index'],
                                $esConfig['indices']['news']['type'],
                                $e->getCode(),
                                $e->getMessage());
            Log::info($message);
        }

        // Bury the job for later inspection
        JobBurier::create($job, function($theJob) {
            // The queue driver does not support bury.
            $theJob->delete();
        })->bury();

        return [
            'status' => 'fail',
            'message' => $message
        ];
    }
}