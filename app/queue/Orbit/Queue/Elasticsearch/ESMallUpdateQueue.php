<?php namespace Orbit\Queue\ElasticSearch;
/**
 * Update Elasticsearch index when mall has been updated.
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
use Log;

class ESMallUpdateQueue
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

        $esConfig = Config::get('orbit.elasticsearch');
        $geofence = MerchantGeofence::getDefaultValueForAreaAndPosition($mallId);

        try {
            // check exist elasticsearch index
            $params_search = [
                'index' => Config::get('orbit.elasticsearch.indices.malldata.index'),
                'type' => Config::get('orbit.elasticsearch.indices.malldata.type'),
                'body' => [
                    'query' => [
                        'match' => [
                            '_id' => $mall->merchant_id
                        ]
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);

            $response = NULL;
            if ($response_search['hits']['total'] > 0) {
                $params = [
                    'index' => Config::get('orbit.elasticsearch.indices.malldata.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.malldata.type'),
                    'id' => $mall->merchant_id,
                    'body' => [
                        'doc' => [
                            'name'            => $mall->name,
                            'description'     => $mall->description,
                            'address_line'    => trim(implode("\n", [$mall->address_line1, $mall->address_line2, $mall->address_line2])),
                            'city'            => $mall->city,
                            'country'         => $mall->Country->name,
                            'phone'           => $mall->phone,
                            'operating_hours' => $mall->operating_hours,
                            'object_type'     => $mall->object_type,
                            'status'          => $mall->status,
                            'ci_domain'       => $mall->ci_domain,
                            'position'        => [
                                'lon' => $geofence->longitude,
                                'lat' => $geofence->latitude
                            ],
                            'area' => [
                                'type'        => 'polygon',
                                'coordinates' => $geofence->area
                            ]
                        ]
                    ]
                ];

                $response = $this->poster->update($params);
            } else {
                $params = [
                    'index' => Config::get('orbit.elasticsearch.indices.malldata.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.malldata.type'),
                    'id' => $mall->merchant_id,
                    'body' => [
                        'name'            => $mall->name,
                        'description'     => $mall->description,
                        'address_line'    => trim(implode("\n", [$mall->address_line1, $mall->address_line2, $mall->address_line2])),
                        'city'            => $mall->city,
                        'country'         => $mall->Country->name,
                        'phone'           => $mall->phone,
                        'operating_hours' => $mall->operating_hours,
                        'object_type'     => $mall->object_type,
                        'status'          => $mall->status,
                        'ci_domain'       => $mall->ci_domain,
                        'position'        => [
                            'lon' => $geofence->longitude,
                            'lat' => $geofence->latitude
                        ],
                        'area' => [
                            'type'        => 'polygon',
                            'coordinates' => $geofence->area
                        ]
                    ]
                ];

                $response = $this->poster->index($params);
            }

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
            //   "created": false
            // }
            //
            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
                                $job->getJobId(),
                                $esConfig['indices']['malldata']['index'],
                                $esConfig['indices']['malldata']['type']);
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
                                $esConfig['indices']['malldata']['index'],
                                $esConfig['indices']['malldata']['type'],
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