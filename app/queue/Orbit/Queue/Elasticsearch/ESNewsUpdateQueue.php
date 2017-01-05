<?php namespace Orbit\Queue\ElasticSearch;
/**
 * Update Elasticsearch index when news has been updated.
 *
 * @author kadek <kadek@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use DB;
use News;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Log;

class ESNewsUpdateQueue
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
        $news = News::with(
                            'translations.media_orig',
                            'campaignLocations.categories',
                            'esCampaignLocations.geofence',
                            'keywords',
                            'campaignObjectPartners',
                            'adverts.media_orig'
                        )
                    ->select(DB::raw("
                        {$prefix}news.*,
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
                    ->where('news.news_id', $newsId)
                    ->where('news.object_type', 'news')
                    ->whereRaw("{$prefix}news.status = 'active'")
                    ->havingRaw("campaign_status = 'ongoing'")
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
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.news.index'),
                'type' => Config::get('orbit.elasticsearch.indices.news.type'),
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
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.news.index'),
                'type' => Config::get('orbit.elasticsearch.indices.news.type'),
                'id' => $news->news_id,
                'body' => []
            ];

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
                    "parent_id" => $esCampaignLocation->parent_id,
                    "name" => $esCampaignLocation->name,
                    "object_type" => $esCampaignLocation->object_type,
                    "city" => $esCampaignLocation->city,
                    "province" => $esCampaignLocation->province,
                    "country" => $esCampaignLocation->country,
                    "position" => [
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
            foreach ($news->campaignObjectPartners as $campaignObjectPartner) {
                $partnerIds[] = $campaignObjectPartner->partner_id;
            }

            $advertIds = array();
            foreach ($news->adverts as $advertCollection) {
                $advertIds[] = $advertCollection->advert_id;
            }

            $translations = array();
            foreach ($news->translations as $translationCollection) {
                $translation = array(
                    'name' => $translationCollection->news_name,
                    'description' => $translationCollection->description,
                    'language_id' => $translationCollection->merchant_language_id,
                    'image_url' => NULL
                );

                foreach ($translationCollection->media_orig as $media) {
                    $translation['image_url'] = $media->path;
                }
                $translations[] = $translation;
            }

            $body = [
                'news_id' => $news->news_id,
                'name' => $news->news_name,
                'description' => $news->description,
                'object_type' => $news->object_type,
                'begin_date' => date('Y-m-d', strtotime($news->begin_date)) . 'T' . date('H:i:s', strtotime($news->begin_date)) . 'Z',
                'end_date' => date('Y-m-d', strtotime($news->end_date)) . 'T' . date('H:i:s', strtotime($news->end_date)) . 'Z',
                'status' => $news->status,
                'campaign_status' => $news->campaign_status,
                'is_all_gender' => $news->is_all_gender,
                'is_all_age' => $news->is_all_age,
                'category_ids' => $categoryIds,
                'translation' => $translations,
                'keywords' => $keywords,
                'partner_ids' => $partnerIds,
                'advert_ids' => $advertIds,
                'link_to_tenant' => $linkToTenants
            ];

            if ($response_search['hits']['total'] > 0) {
                $params['body'] = [
                    'doc' => $body
                ];
                $response = $this->poster->update($params);
            } else {
                $params['body'] = $body;
                $response = $this->poster->index($params);
            }

            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

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
            // Bury the job for later inspection
            JobBurier::create($job, function($theJob) {
                // The queue driver does not support bury.
                $theJob->delete();
            })->bury();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['news']['index'],
                                $esConfig['indices']['news']['type'],
                                $e->getCode(),
                                $e->getMessage());
            Log::info($message);

            return [
                'status' => 'fail',
                'message' => $message
            ];
        }
    }
}