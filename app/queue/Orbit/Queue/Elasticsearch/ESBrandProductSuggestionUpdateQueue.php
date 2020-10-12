<?php

namespace Orbit\Queue\Elasticsearch;

use BrandProduct;
use Config;
use DB;
use Elasticsearch\ClientBuilder as ESBuilder;
use Exception;
use Log;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Orbit\FakeJob;
use Queue;
use Tenant;

/**
 * Update brand product ES suggestion.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ESBrandProductSuggestionUpdateQueue
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

        $brandProductId = $data['brand_product_id'];
        $brandProduct = BrandProduct::with([
                'brand_product_variants.variant_options'
            ])
            ->select(
                'brand_products.brand_product_id',
                'brand_products.product_name',
                'brand_products.brand_id',
                DB::raw("{$prefix}base_merchants.name as brand_name"),
                DB::raw("{$prefix}countries.name as country_name")
            )
            ->join('base_merchants', 'brand_products.brand_id', '=',
                'base_merchants.base_merchant_id'
            )
            ->join('countries', 'base_merchants.country_id', '=',
                'countries.country_id'
            )
            ->where('brand_product_id', $brandProductId)
            ->where('brand_products.status', 'active')
            ->first();

        try {
            // check exist elasticsearch index
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.product_suggestions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.product_suggestions.type'),
                'body' => [
                    'query' => [
                        'match' => [
                            '_id' => $brandProductId
                        ]
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);

            // delete suggestion document if the status inactive
            if ($response_search['hits']['total'] > 0 && empty($brandProduct)) {
                $paramsDelete = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.product_suggestions.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.product_suggestions.type'),
                    'id' => $brandProductId
                ];
                $responseDelete = $this->poster->delete($paramsDelete);

                ElasticsearchErrorChecker::throwExceptionOnDocumentError($responseDelete);

                $job->delete();

                $message = sprintf('[Job ID: `%s`] Elasticsearch Delete Document in Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
                                    $job->getJobId(),
                                    $esConfig['indices']['product_suggestions']['index'],
                                    $esConfig['indices']['product_suggestions']['type']);
                Log::info($message);

                return [
                    'status' => 'ok',
                    'message' => $message
                ];
            } else if (empty($brandProduct)) {
                $job->delete();

                return [
                    'status' => 'fail',
                    'message' => sprintf('[Job ID: `%s`] Brand Product %s is not found.', $job->getJobId(), $brandProductId)
                ];
            }

            $response = NULL;
            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.product_suggestions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.product_suggestions.type'),
                'id' => $brandProduct->brand_product_id,
                'body' => []
            ];

            // generate input
            $strings = $brandProduct->product_name;
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

            $linkedMerchantIds = [];
            foreach($brandProduct->brand_product_variants as $bpVariant) {
                foreach($bpVariant->variant_options as $variantOption) {
                    if ($variantOption->option_type === 'merchant') {
                        if (! in_array(
                                $variantOption->option_id,
                                $linkedMerchantIds
                            )
                        ) {
                            $linkedMerchantIds[] = $variantOption->option_id;
                        }
                    }
                }
            }

            // Get list of stores
            $brandProductCities = [];
            if (count($linkedMerchantIds) > 0) {
                $linkedStores = Tenant::select(
                        DB::raw('mall.city as city_name')
                    )
                    ->join('merchants as mall', 'merchants.parent_id', '=',
                        DB::raw('mall.merchant_id')
                    )
                    ->join('mall_cities as cities', DB::raw('mall.city'), '=',
                        DB::raw('cities.city')
                    )
                    ->whereIn('merchants.merchant_id', $linkedMerchantIds)
                    ->get();

                foreach ($linkedStores as $linkedStore) {
                    $brandProductCities[] = $linkedStore->city_name;
                }
            }

            $suggest = [
                'input'   => $combo,
                'output'  => $brandProduct->product_name,
                'payload' => [
                    'id' => $brandProduct->brand_product_id,
                    'type' => 'brand_product',
                    'brand_name' => $brandProduct->brand_name,
                ],
            ];

            $esBody = [
                'product_name'  => $brandProduct->product_name,
                'country'    => [$brandProduct->country_name],
                'city'       => $brandProductCities,
                'brand_id'      => $brandProduct->brand_id,
                'brand_name'    => $brandProduct->brand_name,
                'suggest_id' => $suggest,
                'suggest_en' => $suggest,
            ];

            if ($response_search['hits']['total'] > 0) {
                $params['body'] = ['doc' => $esBody];
                $response = $this->poster->update($params);
            } else {
                $params['body'] = $esBody;
                $response = $this->poster->index($params);
            }

            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            $indexParams['index']  = $esPrefix . Config::get('orbit.elasticsearch.indices.product_suggestions.index');
            $this->poster->indices()->refresh($indexParams);

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
                                $job->getJobId(),
                                $esConfig['indices']['product_suggestions']['index'],
                                $esConfig['indices']['product_suggestions']['type']);
            Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['product_suggestions']['index'],
                                $esConfig['indices']['product_suggestions']['type'],
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