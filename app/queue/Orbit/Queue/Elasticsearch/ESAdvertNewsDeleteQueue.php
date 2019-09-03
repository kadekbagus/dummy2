<?php namespace Orbit\Queue\Elasticsearch;

use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use DB;
use Advert;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Orbit\FakeJob;
use Log;
use Carbon\Carbon as Carbon;
use Orbit\Helper\MongoDB\Client as MongoClient;

/**
 * Job that handle deleting advert news record for a specific news.
 * Don't run this job directly, because it doesn't check if the news is valid/available/active or not.
 * This job meant to be run after running a script that detect if news is not available/active anymore.
 * (or via CLI (tinker) when we are doing maintenance/must remove them anyway)
 *
 * @author Budi <budi@dominopos.com>
 */
class ESAdvertNewsDeleteQueue
{
    /**
     * Poster. The object which post the data to external system.
     *
     * @var poster.
     */
    protected $poster = NULL;

    private $advertData = null;

    /**
     * Class constructor.
     *
     * @param string $poster Object used to post the data.
     * @return void
     */
    public function __construct($poster = 'default', $advertData = null)
    {
        if ($poster === 'default') {
            $this->poster = ESBuilder::create()
                                     ->setHosts(Config::get('orbit.elasticsearch.hosts'))
                                     ->build();
        } else {
            $this->poster = $poster;
        }

        $this->advertData = $advertData;
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
        $mongoConfig = Config::get('database.mongodb');

        //Get now time
        $timezone = 'Asia/Jakarta'; // now with jakarta timezone
        $timestamp = date("Y-m-d H:i:s");
        $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
        $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();

        if (empty($this->advertData)) {
            $this->advertData = Advert::select('adverts.advert_id', 'advert_placements.placement_type', 'advert_placements.placement_order', 'adverts.start_date', 'adverts.end_date', 'adverts.status', 'adverts.is_all_location')
                                    ->join('advert_link_types', 'adverts.advert_link_type_id', '=', 'advert_link_types.advert_link_type_id')
                                    ->join('advert_placements', 'advert_placements.advert_placement_id', '=', 'adverts.advert_placement_id')
                                    ->whereIn('advert_placements.placement_type', ['preferred_list_regular', 'preferred_list_large', 'featured_list'])
                                    ->where('advert_link_types.advert_type', 'news')
                                    ->where('adverts.end_date', '>=', date("Y-m-d", strtotime($dateTime)))
                                    ->where('adverts.link_object_id', $newsId)
                                    ->groupBy('adverts.advert_id')
                                    ->orderBy('adverts.advert_id')
                                    ->get();
        }

        try {
            $newsName = '';

            // 1 advert 1 document
            foreach ($this->advertData as $adverts) {
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

                if ($response_search['hits']['total'] > 0) {
                    $newsName = $response_search['hits']['hits'][0]['_source']['name'];
                    $params = [
                        'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.advert_news.index'),
                        'type' => Config::get('orbit.elasticsearch.indices.advert_news.type'),
                        'id' => $response_search['hits']['hits'][0]['_id'],
                    ];

                    $response = $this->poster->delete($params);

                    // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
                    ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);
                }

            }

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Advert News Delete Index; Status: OK; ES Index Name: %s; ES Index Type: %s; News ID: %s; News Name: %s',
                                $job->getJobId(),
                                $esConfig['indices']['advert_news']['index'],
                                $esConfig['indices']['advert_news']['type'],
                                $newsId,
                                $newsName);
            Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];

        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Elasticsearch Advert News Delete Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['advert_news']['index'],
                                $esConfig['indices']['advert_news']['type'],
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
