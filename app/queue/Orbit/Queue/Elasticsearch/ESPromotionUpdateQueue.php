<?php namespace Orbit\Queue\Elasticsearch;
/**
 * Update Elasticsearch index when promotions has been updated.
 *
 * @author kadek <kadek@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use DB;
use News;
use Advert;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Log;
use Orbit\FakeJob;
use Orbit\Helper\MongoDB\Client as MongoClient;
use ObjectSponsor;
use SponsorCreditCard;
use ObjectSponsorCreditCard;

class ESPromotionUpdateQueue
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
        $mongoConfig = Config::get('database.mongodb');

        $newsId = $data['news_id'];
        $news = News::with(
                            'translations.media_orig',
                            'campaignLocations.categories',
                            'esCampaignLocations.geofence',
                            'campaignObjectPartners',
                            'promotionAdverts.media_orig',
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
                    ->where('news.object_type', 'promotion')
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
            // check exist elasticsearch index
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.promotions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.promotions.type'),
                'body' => [
                    'query' => [
                        'match' => [
                            '_id' => $news->news_id
                        ]
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);

            $response = NULL;
            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.promotions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.promotions.type'),
                'id' => $news->news_id,
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

            // get rating by location
            $locationRating = array();
            $queryString = [
                'object_id'   => $news->news_id,
                'object_type' => 'promotion'
            ];

            $mongoClient = MongoClient::create($mongoConfig);
            $endPoint = "review-counters";
            $response = $mongoClient->setQueryString($queryString)
                                    ->setEndPoint($endPoint)
                                    ->request('GET');

            $listOfRecLocation = $response->data;

            if (! empty($listOfRecLocation->records)) {
                $countryRating = array();
                foreach ($listOfRecLocation->records as $rating) {
                    // by country
                    $countryId = $rating->country_id;
                    $countryRating[$countryId]['total'] = (! empty($countryRating[$countryId]['total'])) ? $countryRating[$countryId]['total'] : 0;
                    $countryRating[$countryId]['review'] = (! empty($countryRating[$countryId]['review'])) ? $countryRating[$countryId]['review'] : 0;

                    $countryRating[$countryId]['total'] = $countryRating[$countryId]['total'] + ((double) $rating->average * (double) $rating->counter);
                    $countryRating[$countryId]['review'] = $countryRating[$countryId]['review'] + $rating->counter;

                    $locationRating['rating_' . $countryId] = ((double) $countryRating[$countryId]['total'] / (double) $countryRating[$countryId]['review']) + 0.00001;
                    $locationRating['review_' . $countryId] = (double) $countryRating[$countryId]['review'];

                    // by country and city
                    $locationRating['rating_' . $rating->country_id . '_' . str_replace(" ", "_", trim(strtolower($rating->city), " "))] = $rating->average + 0.00001;
                    $locationRating['review_' . $rating->country_id . '_' . str_replace(" ", "_", trim(strtolower($rating->city), " "))] = $rating->counter;
                }
            }

            // get rating by mall
            $mallRating = array();
            $endPoint = "mall-review-counters";
            $response = $mongoClient->setQueryString($queryString)
                                    ->setEndPoint($endPoint)
                                    ->request('GET');

            $listOfRecMall = $response->data;
            if(! empty($listOfRecMall->records)) {
                foreach ($listOfRecMall->records as $rating) {
                    $mallRating['rating_' . $rating->location_id] = $rating->average + 0.00001;
                    $mallRating['review_' . $rating->location_id] = $rating->counter;
                }
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

            // promotion sponsor provider
            // Get sponsor provider wallet
            $sponsorProviders = ObjectSponsor::select('object_sponsor.object_sponsor_id','sponsor_providers.sponsor_provider_id','media.path','media.cdn_url', 'object_sponsor.is_all_credit_card')
                                            ->leftJoin('sponsor_providers','sponsor_providers.sponsor_provider_id', '=', 'object_sponsor.sponsor_provider_id')
                                            ->leftJoin('media', function($q){
                                                    $q->on('media.object_id', '=', 'sponsor_providers.sponsor_provider_id')
                                                      ->on('media.media_name_long', '=', DB::raw('"sponsor_provider_logo_orig"'));
                                              })
                                            ->where('object_sponsor.object_type', 'promotion')
                                            ->where('sponsor_providers.status', 'active')
                                            ->where('object_sponsor.object_id', $news->news_id);

            $sponsorProviderWallets = clone $sponsorProviders;
            $sponsorProviderWallets = $sponsorProviderWallets->where('sponsor_providers.object_type', 'ewallet')
                                                        ->get();

            $sponsorProviderES = array();
            if (!$sponsorProviderWallets->isEmpty()){
                $ewallet = array();
                foreach ($sponsorProviderWallets as $sponsorProviderWallet) {
                    $ewallet['sponsor_id'] = $sponsorProviderWallet->sponsor_provider_id;
                    $ewallet['sponsor_type'] = 'ewallet';
                    $ewallet['bank_id'] = null;
                    $ewallet['logo_url'] = $sponsorProviderWallet->path;
                    $ewallet['logo_cdn_url'] = $sponsorProviderWallet->cdn_url;
                }
                $sponsorProviderES[] = $ewallet;
            }

            // Get sponsor provider bank
            $sponsorProviderBanks = $sponsorProviders->where('sponsor_providers.object_type', 'bank')
                                                     ->get();

            if (!$sponsorProviderBanks->isEmpty()){
                foreach ($sponsorProviderBanks as $sponsorProviderBank) {
                    if ($sponsorProviderBank->is_all_credit_card === 'Y') {
                        // get all credit_card
                        $sponsorProviderCC = SponsorCreditCard::select('sponsor_credit_card_id')
                                                              ->where('sponsor_provider_id', '=', $sponsorProviderBank->sponsor_provider_id);
                    } elseif ($sponsorProviderBank->is_all_credit_card === 'N') {
                        // get credit_card id by user selection
                        $sponsorProviderCC = ObjectSponsorCreditCard::select('sponsor_credit_cards.sponsor_credit_card_id')
                                                                    ->leftJoin('sponsor_credit_cards', 'sponsor_credit_cards.sponsor_credit_card_id', '=', 'object_sponsor_credit_card.sponsor_credit_card_id')
                                                                    ->where('object_sponsor_credit_card.object_sponsor_id', $sponsorProviderBank->object_sponsor_id);
                    }

                    $sponsorProviderCC = $sponsorProviderCC->get();

                    if (!$sponsorProviderCC->isEmpty()) {
                        $cc = array();
                        foreach ($sponsorProviderCC as $cc) {
                            $cc['sponsor_id'] = $cc->sponsor_credit_card_id;
                            $cc['sponsor_type'] = 'credit_card';
                            $cc['bank_id'] = $sponsorProviderBank->sponsor_provider_id;
                            $cc['logo_url'] = $sponsorProviderBank->path;
                            $cc['logo_cdn_url'] = $sponsorProviderBank->cdn_url;
                        }
                        $sponsorProviderES[] = $cc;
                    }
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
                'status'               => $news->status,
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
                'location_rating'      => $locationRating,
                'mall_rating'          => $mallRating,
                'sponsor_provider'     => $sponsorProviderES
            ];

            $body = array_merge($body, $translationBody);

            if ($response_search['hits']['total'] > 0) {
                $params = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.promotions.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.promotions.type'),
                    'id' => $response_search['hits']['hits'][0]['_id']
                ];

                $response = $this->poster->delete($params);
            }

            $params['body'] = $body;
            $response = $this->poster->index($params);

            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            // update suggestion
            $fakeJob = new FakeJob();
            $esQueue = new \Orbit\Queue\Elasticsearch\ESPromotionSuggestionUpdateQueue();
            $suggestion = $esQueue->fire($fakeJob, ['news_id' => $newsId]);

            $esAdvertQueue = new \Orbit\Queue\Elasticsearch\ESAdvertPromotionUpdateQueue();
            $advertUpdate = $esAdvertQueue->fire($fakeJob, ['news_id' => $newsId]);

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s; News ID: %s; Promotion Name: %s',
                                $job->getJobId(),
                                $esConfig['indices']['promotions']['index'],
                                $esConfig['indices']['promotions']['type'],
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
                                $esConfig['indices']['promotions']['index'],
                                $esConfig['indices']['promotions']['type'],
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