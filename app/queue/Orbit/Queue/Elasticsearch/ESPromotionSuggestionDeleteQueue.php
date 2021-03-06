<?php namespace Orbit\Queue\Elasticsearch;
/**
 * Delete Elasticsearch index when promotion has been deleted.
 *
 * @author shelgi <shelgi@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use News;
use DB;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;

class ESPromotionSuggestionDeleteQueue
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
     *
     * @author kadek <kadek@dominopos.com>
     * @param Job $job
     * @param array $data[
     *                    'merchant_id' => NUM // Mall ID
     * ]
     * @return void
     * @todo Make this testable
     */
    public function fire($job, $data)
    {
        $prefix = DB::getTablePrefix();
        $newsId = $data['news_id'];
        $news = News::select(DB::raw("
                        {$prefix}news.news_id,
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
                    ->where('news.object_type', 'promotion')
                    ->havingRaw("campaign_status in ('stopped', 'expired')")
                    ->first();

        if (! is_object($news)) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] News ID %s is not found.', $job->getJobId(), $newsId)
            ];
        }

        $esConfig = Config::get('orbit.elasticsearch');
        $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');

        try {
            // Delete mall level suggestion
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.promotion_mall_level_suggestions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.promotion_mall_level_suggestions.type'),
                'body' => [
                    'from' => 0,
                    'size' => 200,
                    'query' => [
                        'match' => [
                            'id' => $news->news_id
                        ]
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);
            if ($response_search['hits']['total'] > 0) {
                foreach ($response_search['hits']['hits'] as $val) {
                    $paramsDelete = [
                        'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.promotion_mall_level_suggestions.index'),
                        'type' => Config::get('orbit.elasticsearch.indices.promotion_mall_level_suggestions.type'),
                        'id' =>  $val['_id']
                    ];

                    $responseDelete = $this->poster->delete($paramsDelete);
                }
            }
            $indexParamsMallLevel['index']  = $esPrefix . Config::get('orbit.elasticsearch.indices.promotion_mall_level_suggestions.index');
            $this->poster->indices()->refresh($indexParamsMallLevel);

            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.promotion_suggestions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.promotion_suggestions.type'),
                'id' => $news->news_id
            ];

            $response = $this->poster->delete($params);

            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            $indexParams['index']  = $esPrefix . Config::get('orbit.elasticsearch.indices.promotion_suggestions.index');
            $this->poster->indices()->refresh($indexParams);

            // Safely delete the object
            $job->delete();

            return [
                'status' => 'ok',
                'message' => sprintf('[Job ID: `%s`] Elasticsearch Delete Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
                                $job->getJobId(),
                                $esConfig['indices']['promotions']['index'],
                                $esConfig['indices']['promotions']['type'])
            ];
        } catch (Exception $e) {
            // Bury the job for later inspection
            JobBurier::create($job, function($theJob) {
                // The queue driver does not support bury.
                $theJob->delete();
            })->bury();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Elasticsearch Delete Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['promotions']['index'],
                                $esConfig['indices']['promotions']['type'],
                                $e->getCode(),
                                $e->getMessage())
            ];
        }
    }
}