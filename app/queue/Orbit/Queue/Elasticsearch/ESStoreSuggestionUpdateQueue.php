<?php namespace Orbit\Queue\ElasticSearch;
/**
 * Update Elasticsearch index when store/tenant has been updated.
 *
 * @author kadek <kadek@dominopos.com>
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

class ESStoreSuggestionUpdateQueue
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
                        ->orderBy('merchants.created_at', 'asc')
                        ->get();

        try {
            // check exist elasticsearch index
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.store_suggestions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.store_suggestions.type'),
                'body' => [
                    'query' => [
                        'filtered' => [
                            'filter' => [
                                'and' => [
                                    [
                                        'match' => [
                                            'name.raw' => $storeName
                                        ]
                                    ],
                                    [
                                        'match' => [
                                            'country.raw' => $countryName
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
            if ($response_search['hits']['total'] > 0) {
                $params = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.store_suggestions.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.store_suggestions.type'),
                    'id' => $response_search['hits']['hits'][0]['_id']
                ];

                $response = $this->poster->delete($params);
            }

            if ($store->isEmpty()) {
                $job->delete();

                return [
                    'status' => 'fail',
                    'message' => sprintf('[Job ID: `%s`] Store Name %s is not found.', $job->getJobId(), $storeName)
                ];
            }

            $response = NULL;
            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.store_suggestions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.store_suggestions.type'),
                'id' => $store[0]->merchant_id,
                'body' => []
            ];

            $country = array();
            $city = array();
            foreach($store as $_store) {
                if (! in_array($_store->city, $city)) {
                    $city[] = $_store->city;
                }

                if (! in_array($_store->country, $country)) {
                    $country[] = $_store->country;
                }
            }

            // generate input
            $textName = $store[0]->name;
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
                'output'  => $store[0]->name,
                'payload' => ['id' => $store[0]->merchant_id, 'type' => 'store']
            ];

            $body = [
                'name'       => $store[0]->name,
                'country'    => $country,
                'city'       => $city,
                'suggest_id' => $suggest,
                'suggest_en' => $suggest,
                'suggest_zh' => $suggest,
                'suggest_ms' => $suggest
            ];

            $params['body'] = $body;
            $response = $this->poster->index($params);

            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            $indexParams['index']  = $esPrefix . Config::get('orbit.elasticsearch.indices.store_suggestions.index');
            $this->poster->indices()->refresh($indexParams);

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s; Store ID : %s; Store Name: %s',
                                $job->getJobId(),
                                $esConfig['indices']['stores']['index'],
                                $esConfig['indices']['stores']['type'],
                                $store[0]->merchant_id,
                                $store[0]->name);
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