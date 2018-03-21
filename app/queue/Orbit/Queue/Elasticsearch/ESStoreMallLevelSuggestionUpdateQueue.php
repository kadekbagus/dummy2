<?php namespace Orbit\Queue\Elasticsearch;
/**
 * Update Elasticsearch store mall level suggestion index when store has been updated.
 *
 * @author firmansyah <firmansyah@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use DB;
use Tenant;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Log;
use Queue;
use Orbit\FakeJob;

class ESStoreMallLevelSuggestionUpdateQueue
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
        $updateRelated = (empty($data['updated_related']) ? FALSE : $data['updated_related']);
        $storeName = $data['name'];
        $countryName = $data['country'];

        $store = Tenant::select(
                            'merchants.merchant_id',
                            'merchants.parent_id',
                            'merchants.name',
                            DB::raw('oms.city'),
                            DB::raw('oms.country'))
                        ->join(DB::raw("(
                            select merchant_id, name, status, parent_id, city,
                                   province, country, address_line1, operating_hours
                            from {$prefix}merchants
                            where status = 'active'
                                and object_type = 'mall'
                            ) as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                        ->whereRaw("{$prefix}merchants.status = 'active'")
                        ->whereRaw("oms.status = 'active'")
                        ->where('merchants.name', '=', $storeName)
                        ->whereRaw("oms.country = '{$countryName}'")
                        ->orderBy('merchants.created_at', 'asc');

        $_store = clone $store;

        $store = $store->first();

        $mallIds = null;
        if (! empty($store)) {
            $storeMalls = $_store->groupBy('merchants.parent_id')
                                  ->get();

            // Re-group mallids per $take, this issue to reduce maximum calculation (250) in elasticseach
            if(! $storeMalls->isEmpty()) {
                $keyArray = 0;
                $take = Config::get('orbit.elasticsearch.maximum_separated_mall_id');

                foreach ($storeMalls as $key => $couponMall) {
                    if ($key % $take == 0) {
                        $keyArray ++;
                    }
                    $mallIds[$keyArray][] = $storeMalls[$key]->parent_id;
                }
            }
        }

        try {
            // check exist elasticsearch index
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.store_mall_level_suggestions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.store_mall_level_suggestions.type'),
                'body' => [
                    // limit default es is 10
                    'from' => 0,
                    'size' => 50,
                    // query
                    'query' => [
                        'filtered' => [
                            'filter' => [
                                'and' => [
                                    [
                                        'match' => [
                                            'name.raw' => $storeName
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);

            // delete the store document if the status inactive
            if ($response_search['hits']['total'] > 0 && count($store) === 0) {
                foreach ($response_search['hits']['hits'] as $val) {
                    $params = [
                        'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.store_mall_level_suggestions.index'),
                        'type' => Config::get('orbit.elasticsearch.indices.store_mall_level_suggestions.type'),
                        'id' => $val['_id']
                    ];

                    $response = $this->poster->delete($params);
                }
            } elseif (count($store) === 0) {
                $job->delete();

                return [
                    'status' => 'fail',
                    'message' => sprintf('[Job ID: `%s`] Store Name %s is not found.', $job->getJobId(), $storeName)
                ];
            }

            // Insert to ES with split data mall_id
            if (! empty($store) && $mallIds != null) {
                // Delete first old data
                if ($response_search['hits']['total'] > 0) {
                    foreach ($response_search['hits']['hits'] as $val) {
                        $paramsDelete = [
                            'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.store_mall_level_suggestions.index'),
                            'type' => Config::get('orbit.elasticsearch.indices.store_mall_level_suggestions.type'),
                            'id' =>  $val['_id']
                        ];

                        $responseDelete = $this->poster->delete($paramsDelete);
                    }
                }

                // Insert new data
                foreach ($mallIds as $key => $value) {
                    $response = NULL;
                    $params = [
                        'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.store_mall_level_suggestions.index'),
                        'type' => Config::get('orbit.elasticsearch.indices.store_mall_level_suggestions.type'),
                        // 'id' => $store->merchant_id,
                        'body' => []
                    ];

                    // generate input
                    $textName = $store->name;
                    $explode = explode(' ', $textName);
                    $count = count($explode);

                    $input = array();
                    for($a = 0; $a < $count; $a++) {
                        $textName = '';
                        for($b = $a; $b < $count; $b++) {
                            $textName .= $explode[$b] . ' ';
                        }
                        $input[] = substr($textName, 0, -1);
                    }

                    $suggest = [
                        'input'   => $input,
                        'output'  => $store->name,
                        'payload' => ['id' => $store->merchant_id, 'type' => 'store']
                    ];

                    $body = [
                        'name'       => $store->name,
                        'mall_id'    => $mallIds[$key],
                        'suggest_id' => $suggest,
                        'suggest_en' => $suggest,
                        'suggest_zh' => $suggest,
                        'suggest_ms' => $suggest
                    ];

                    $params['body'] = $body;
                    $response = $this->poster->index($params);

                    // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
                    ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

                    $indexParams['index']  = $esPrefix . Config::get('orbit.elasticsearch.indices.store_mall_level_suggestions.index');
                    $this->poster->indices()->refresh($indexParams);
                }
            }

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s; Store ID : %s; Store Name: %s',
                                $job->getJobId(),
                                $esConfig['indices']['stores']['index'],
                                $esConfig['indices']['stores']['type'],
                                $store->merchant_id,
                                $store->name);
            Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];

        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['stores']['index'],
                                $esConfig['indices']['stores']['type'],
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