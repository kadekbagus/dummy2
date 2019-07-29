<?php namespace Orbit\Queue\Elasticsearch;
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
                            // 'merchants.parent_id',
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
            $strings = $store[0]->name;
            $strings = preg_replace('/[^A-Za-z0-9 ] /', '', $strings);
            $strings = str_replace(['"', "'"], '', $strings);

            $words = explode(" ", $strings);

            $num = count($words);

            // The total number of possible combinations
            $total = pow(2, $num);

            $combo = [];
            // Loop through each possible combination
            // Warning, higher word counts will also increase CPU usage.
            for ($i = 0; $i < $total; $i++) {
                    //For each combination check if each bit is set
                $save = '';
                for ($j = 0; $j < $total; $j++) {
                    //Is bit $j set in $i?
                    if (pow(2, $j) & $i) {
                        // echo $words[$j] . ' ';
                        $save = $save . $words[$j] . ' ';
                    }
                }
                $combo[] = trim($save);
                // echo "\n";
            }

            // remove first empty element
            $combo = array_splice($combo, 1, count($combo));

            // sort by most word counts and leftest word occurence first
            // @todo: there is a little bit issue on "leftest word occurence first"
            //     some cases it is not sorting as expected
            usort($combo, function($a, $b) use ($words) {
                $totalA = 0;
                $totalB = 0;

                $componentA = explode(" ", $a);
                foreach($componentA as $ca) {
                    $totalA = $totalA + pow(array_search($ca, $words), 2);
                }

                $componentB = explode(" ", $b);
                foreach($componentB as $cb) {
                    $totalB = $totalB + pow(array_search($cb, $words), 2);
                }

                $wordCountA = str_word_count($a);
                $wordCountB = str_word_count($b);

                if ($wordCountB == $wordCountA) {
                    // print_r([$totalA, $totalB]);
                    return $totalA - $totalB;
                } elseif ($wordCountB > $wordCountA) {
                    return 1;
                } elseif ($wordCountB < $wordCountA) {
                    return -1;
                }
            });

            $suggest = [
                'input'   => $combo,
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