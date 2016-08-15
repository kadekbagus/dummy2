<?php namespace Orbit\Queue\ElasticSearch;
/**
 * Delete Elasticsearch index when mall has been deleted.
 *
 * @author Irianto <irianto@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use Mall;
use DB;
use MerchantGeofence;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;

class ESMallDeleteQueue
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
     * @author Irianto <irianto@dominopos.com>
     * @param Job $job
     * @param array $data[
     *                    'merchant_id' => NUM // Mall ID
     * ]
     * @return void
     * @todo Make this testable
     */
    public function fire($job, $data)
    {
        $mallId = $data['mall_id'];
        $mall = Mall::with('country')
                    ->where('status', 'deleted')
                    ->where('merchant_id', $mallId)
                    ->first();

        if (! is_object($mall)) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Mall ID %s is not found.', $job->getJobId(), $mallId)
            ];
        }

        $esConfig = Config::get('orbit.elasticsearch');
        $geofence = MerchantGeofence::getDefaultValueForAreaAndPosition($mallId);
        $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');

        try {
            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.malldata.index'),
                'type' => Config::get('orbit.elasticsearch.indices.malldata.type'),
                'id' => $mall->merchant_id
            ];

            $response = $this->poster->delete($params);

            // Example response when document created:
            // {
            //   "found": true,
            //   "_index": "malls",
            //   "_type": "mall",
            //   "_id": "abcs23",
            //   "_version": 2,
            //   "_shards": {
            //     "total": 2,
            //     "successful": 1,
            //     "failed": 0
            //   }
            // }
            //
            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            // Safely delete the object
            $job->delete();

            return [
                'status' => 'ok',
                'message' => sprintf('[Job ID: `%s`] Elasticsearch Delete Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
                                $job->getJobId(),
                                $esConfig['indices']['malldata']['index'],
                                $esConfig['indices']['malldata']['type'])
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
                                $esConfig['indices']['malldata']['index'],
                                $esConfig['indices']['malldata']['type'],
                                $e->getCode(),
                                $e->getMessage())
            ];
        }
    }
}