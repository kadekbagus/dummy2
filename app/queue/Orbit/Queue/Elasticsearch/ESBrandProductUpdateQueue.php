<?php

namespace Orbit\Queue\Elasticsearch;

use BrandProduct;
use Config;
use DB;
use Elasticsearch\ClientBuilder as ESBuilder;
use Exception;
use Log;
use Orbit\FakeJob;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Orbit\Queue\Elasticsearch\ESBrandProductSuggestionUpdateQueue;
use Tenant;

/**
 * Update Brand Product document on ES.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ESBrandProductUpdateQueue
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
                'brand',
                'brand_product_variants.variant_options',
                'categories',
                'brand_product_main_photo'
            ])
            ->where('brand_product_id', $brandProductId)
            ->first();

        // var_dump($brandProduct->brand_product_variants); die;

        if (! is_object($brandProduct)) {
            // Delete job
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Brand Product ID %s is not found.', $job->getJobId(), $brandProductId)
            ];
        }

        try {
            // check exist elasticsearch index
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.products.index'),
                'type' => Config::get('orbit.elasticsearch.indices.products.type'),
                'body' => [
                    'query' => [
                        'match' => [
                            '_id' => $brandProduct->brand_product_id
                        ]
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);

            $response = NULL;
            $params  = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.products.index'),
                'type' => Config::get('orbit.elasticsearch.indices.products.type'),
                'id' => $brandProduct->brand_product_id,
                'body' => []
            ];

            // Prepare main body
            $lowercaseName = strtolower($brandProduct->product_name);
            $lowercaseName = str_replace(' ', '_', $lowercaseName);

            $body = [
                'product_name' => $brandProduct->product_name,
                'lowercase_name' => $lowercaseName,
                'description' => $brandProduct->product_description,
                'status' => $brandProduct->status,
                'created_at' => $this->esDate($brandProduct->created_at),
                'updated_at' => $this->esDate($brandProduct->updated_at),
                'brand_id' => $brandProduct->brand_id,
                'brand_name' => $brandProduct->brand->name,
                'image_path' => '',
                'image_cdn' => '',
                'skus' => [],
                'product_codes' => [],
                'country' => '',
                'cities' => [],
                'link_to_categories' => [],
                'link_to_stores' => [],
                'link_to_malls' => [],
                'lowest_original_price' => 0.0,
                'highest_original_price' => 0.0,
                'lowest_selling_price' => 0.0,
                'highest_selling_price' => 0.0,
            ];

            // Add linked SKUs
            // Add linked product codes
            $lowestPrice = 0.0;
            $highestPrice = 0.0;
            $lowestOriginalPrice = 0.0;
            $highestOriginalPrice = 0.0;
            $linkedMerchantIds = [];
            foreach($brandProduct->brand_product_variants as $variant) {
                $body['skus'][] = [
                    'sku' => $variant->sku,
                ];

                $body['product_codes'][] = [
                    'product_code' => $variant->product_code,
                ];

                if ($variant->selling_price < $lowestPrice
                    || $lowestPrice == 0.0
                ) {
                    $lowestPrice = $variant->selling_price;
                }

                $highestPrice = $variant->selling_price > $highestPrice
                    ? $variant->selling_price : $highestPrice;

                if ($variant->original_price < $lowestOriginalPrice
                    || $lowestOriginalPrice == 0.0
                ) {
                    $lowestOriginalPrice = $variant->original_price;
                }

                $highestOriginalPrice = $variant->original_price > $highestOriginalPrice
                    ? $variant->original_price : $highestOriginalPrice;

                foreach($variant->variant_options as $variantOption) {
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

            // var_dump($body); die;

            // var_dump($linkedMerchantIds); die;

            // Add linked categories
            foreach($brandProduct->categories as $category) {
                $body['link_to_categories'][] = [
                    'category_id' => $category->category_id,
                    'category_name' => $category->category_name,
                ];
            }

            // Add price
            $body['lowest_selling_price'] = $lowestPrice;
            $body['highest_selling_price'] = $highestPrice;

            $body['lowest_original_price'] = $lowestOriginalPrice;
            $body['highest_original_price'] = $highestOriginalPrice;

            // var_dump($body); die;

            // Add linked stores
            if (count($linkedMerchantIds) > 0) {
                $linkedStores = Tenant::select(
                        'merchants.merchant_id as store_id',
                        'merchants.name as store_name',
                        DB::raw('mall.merchant_id as mall_id'),
                        DB::raw('mall.name as mall_name'),
                        DB::raw('mall.country as country_name'),
                        DB::raw('mall.city as city_name'),
                        DB::raw('X(mall_geofence.position) as lat'),
                        DB::raw('Y(mall_geofence.position) as lon'),
                        DB::raw('cities.mall_city_id as city_id'),
                        DB::raw('baseStore.base_merchant_id as brand_id')
                    )
                    ->join('merchants as mall', 'merchants.parent_id', '=',
                        DB::raw('mall.merchant_id')
                    )
                    ->join('base_stores as baseStore', 'merchants.merchant_id',
                        '=', DB::raw('baseStore.base_store_id')
                    )
                    ->join('mall_cities as cities', DB::raw('mall.city'), '=',
                        DB::raw('cities.city')
                    )
                    ->join('merchant_geofences as mall_geofence',
                        DB::raw('mall.merchant_id'), '=',
                        DB::raw('mall_geofence.merchant_id')
                    )
                    ->whereIn('merchants.merchant_id', $linkedMerchantIds)
                    ->orderBy(DB::raw('mall.name'), 'asc')
                    ->get();
            }

            $linkedMalls = [];
            $linkedCities = [];
            $linkedCountry = '';
            $mallPosition = [];
            foreach($linkedStores as $store) {
                $mallPosition = [];
                if (! empty($store->lat) && ! empty($store->lon)) {
                    $mallPosition['position'] = [
                        'lat' => $store->lat,
                        'lon' => $store->lon,
                    ];
                }

                $body['link_to_stores'][] = [
                    'store_id' => $store->store_id,
                    'store_name' => $store->store_name,
                    'mall_id' => $store->mall_id,
                    'mall_name' => $store->mall_name,
                    'city' => $store->city_name,
                    'country' => $store->country_name,
                    'brand_id' => $store->brand_id,
                ] + $mallPosition;

                if (! array_key_exists($store->mall_id, $linkedMalls)) {
                    $linkedMalls[$store->mall_id] = [
                        'mall_id' => $store->mall_id,
                        'mall_name' => $store->mall_name,
                    ];
                }

                if (! array_key_exists($store->city_id, $linkedCities)) {
                    $linkedCities[$store->city_id] = [
                        'city_id' => $store->city_id,
                        'city_name' => $store->city_name,
                    ];
                }

                if (empty($linkedCountry)) {
                    $linkedCountry = $store->country_name;
                }
            }

            // Add linked mall
            $body['link_to_malls'] = array_values($linkedMalls);

            // Add linked country
            $body['country'] = $linkedCountry;

            // Add linked cities
            $body['cities'] = array_values($linkedCities);

            // Update image path
            $imagePath = '';
            $imageCdn = '';
            foreach($brandProduct->brand_product_main_photo as $mainPhoto) {
                if ($mainPhoto->media_name_long === 'brand_product_main_photo_desktop_thumb') {
                    $body['image_path'] = $mainPhoto->path;
                    $body['image_cdn'] = $mainPhoto->cdn_url;
                    break;
                }
            }

            // var_dump($body); die;

            // Delete old document before inserting/updating new one.
            if ($response_search['hits']['total'] > 0) {
                $params = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.products.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.products.type'),
                    'id' => $response_search['hits']['hits'][0]['_id']
                ];

                $response = $this->poster->delete($params);
            }

            $params['body'] = $body;

            $response = $this->poster->index($params);

            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            $fakeJob = new FakeJob();
            $esQueue = new \Orbit\Queue\Elasticsearch\ESBrandProductSuggestionUpdateQueue();
            $suggestion = $esQueue->fire($fakeJob, ['brand_product_id' => $brandProductId]);

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s; Brand Product ID: %s; Brand Product Name: %s',
                                $job->getJobId(),
                                $esConfig['indices']['products']['index'],
                                $esConfig['indices']['products']['type'],
                                $brandProduct->brand_product_id,
                                $brandProduct->title);
            Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['products']['index'],
                                $esConfig['indices']['products']['type'],
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

    private function esDate($theDate = '')
    {
        return $theDate->format('Y-m-d') . 'T' . $theDate->format('H:i:s') . 'Z';
    }
}