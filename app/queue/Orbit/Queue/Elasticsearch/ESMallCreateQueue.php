<?php namespace Orbit\Queue\ElasticSearch;
/**
 * Update Elasticsearch index when new mall has been created.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use Mall;
use DB;
use MerchantGeofence;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;

class ESMallCreateQueue
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
     * @author Rio Astamal <me@rioastamal.net>
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
                    ->excludeDeleted()
                    ->where('merchant_id', $mallId)
                    ->first();

        if (! is_object($mall)) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Mall ID %s is not found.', $job->getJobId(), $mallId)
            ];
        }

        $geofence = MerchantGeofence::latLong()->areaAsText()
                                    ->where('merchant_id', $mallId)
                                    ->first();
        $esConfig = Config::get('orbit.elasticsearch');

        try {
            $params = [
                'index' => 'malls',
                'type' => 'basic',
                'id' => $mall->merchant_id,
                'body' => [
                    'name' => $mall->name,
                    'description' => $mall->description,
                    'address_line' => trim(implode("\n", [$mall->address_line1, $mall->address_line2, $mall->address_line2])),
                    'city' => $mall->city,
                    'country' => $mall->Country->name,
                    'phone' => $mall->phone,
                    'operating_hours' => $mall->operating_hours,
                    'object_type' => $mall->object_type,
                    'status' => $mall->status,
                    'position' => [
                        'lat' => $geofence->latitude,
                        'long' => $geofence->longitude
                    ],
                    'area' => [
                        'type' => 'polygon',
                        'coordinates' => [
                            MerchantGeofence::transformPolygonToElasticsearch($geofence->area)
                        ]
                    ]
                ]
            ];

            $response = $this->poster->index($params);

            // Example response when document created:
            // {
            //   "_index": "malls",
            //   "_type": "basic",
            //   "_id": "abc123",
            //   "_version": 1,
            //   "_shards": {
            //     "total": 2,
            //     "successful": 1,
            //     "failed": 0
            //   },
            //   "created": true
            // }
            //
            // The indexing considered successful is attribute `successful` on `_shard` is more than 1.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            // Safely delete the object
            $job->delete();

            return [
                'status' => 'ok',
                'message' => sprintf('[Job ID: `%s`] Elasticsearch Create Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
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
                'message' => sprintf('[Job ID: `%s`] Elasticsearch Create Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['malldata']['index'],
                                $esConfig['indices']['malldata']['type'],
                                $e->getCode(),
                                $e->getMessage())
            ];
        }
    }
}