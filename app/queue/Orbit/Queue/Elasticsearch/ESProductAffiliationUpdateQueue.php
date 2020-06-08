<?php

namespace Orbit\Queue\Elasticsearch;

use Product;
use Config;
use DB;
use Elasticsearch\ClientBuilder as ESBuilder;
use Exception;
use Log;
use Orbit\FakeJob;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Orbit\Queue\Elasticsearch\ESProductAffiliationSuggestionUpdateQueue;
use Tenant;

/**
 * Update Product Affiliation document on ES.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ESProductAffiliationUpdateQueue
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

        $productId = $data['product_id'];

        $product = Product::with([
                'country',
                'marketplaces',
                'categories',
                'merchants',
                'product_tags' => function($query) {
                    $query->groupBy('product_tag');
                },
                'media' => function($query) {
                    $query->where('media.media_name_long', 'product_image_orig')
                        ->latest()
                        ->skip(0)
                        ->take(1);
                }
            ])
            ->where('product_id', $productId)
            ->first();

        if (! is_object($product)) {
            // Delete job
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Product ID %s is not found.', $job->getJobId(), $productId)
            ];
        }

        try {
            // check exist elasticsearch index
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.product_affiliations.index'),
                'type' => Config::get('orbit.elasticsearch.indices.product_affiliations.type'),
                'body' => [
                    'query' => [
                        'match' => [
                            '_id' => $product->product_id
                        ],
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);

            $response = NULL;
            $params  = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.product_affiliations.index'),
                'type' => Config::get('orbit.elasticsearch.indices.product_affiliations.type'),
                'id' => $product->product_id,
                'body' => []
            ];

            $brand = $product->merchants->first();

            $brandId = '';
            $brandName = '';
            if (! empty($brand)) {
                $brandId = $brand->base_merchant_id;
                $brandName = $brand->name;
            }

            // Prepare main body
            $lowercaseName = strtolower($product->name);
            $lowercaseName = str_replace(' ', '_', $lowercaseName);

            $body = [
                'product_name' => $product->name,
                'lowercase_name' => $lowercaseName,
                'description' => $product->short_description,
                'status' => $product->status,
                'product_tags' => [],
                'brand_id' => $brandId,
                'brand_name' => $brandName,
                'country' => '',
                'marketplace_names' => [],
                'link_to_categories' => [],
                'link_to_brands' => [],
                'lowest_original_price' => 0.0,
                'highest_original_price' => 0.0,
                'lowest_selling_price' => 0.0,
                'highest_selling_price' => 0.0,
                'image_path' => '',
                'image_cdn' => '',
                'created_at' => $this->esDate($product->created_at),
                'updated_at' => $this->esDate($product->updated_at),
            ];

            // Add linked SKUs
            // Add linked product codes
            $lowestPrice = 0.0;
            $highestPrice = 0.0;
            $lowestOriginalPrice = 0.0;
            $highestOriginalPrice = 0.0;
            $linkedMarketplaces = [];
            foreach($product->marketplaces as $marketplace) {
                if (empty($marketplace->selling_price)
                    || $marketplace->selling_price <= 0.0
                ) {
                    continue;
                }

                if ($marketplace->selling_price < $lowestPrice
                    || $lowestPrice == 0.0
                ) {
                    $lowestPrice = $marketplace->selling_price;
                }

                $highestPrice = $marketplace->selling_price > $highestPrice
                    ? $marketplace->selling_price : $highestPrice;

                if ($marketplace->original_price < $lowestOriginalPrice
                    || $lowestOriginalPrice == 0.0
                ) {
                    $lowestOriginalPrice = $marketplace->original_price;
                }

                $highestOriginalPrice = $marketplace->original_price > $highestOriginalPrice
                    ? $marketplace->original_price : $highestOriginalPrice;

                $marketplaceId = $marketplace->marketplace_id;
                if (! array_key_exists($marketplaceId, $linkedMarketplaces)) {
                    $linkedMarketplaces[$marketplaceId] = [
                        'marketplace_name' => $marketplace->name,
                    ];
                }
            }

            $linkedMarketplaces = array_values($linkedMarketplaces);
            $body['marketplace_names'] = $linkedMarketplaces;

            // Add linked categories
            foreach($product->categories as $category) {
                $body['link_to_categories'][] = [
                    'category_id' => $category->category_id,
                    'category_name' => $category->category_name,
                ];
            }

            // Add link to brands
            $linkToBrands = [];
            foreach($product->merchants as $brand) {
                $linkToBrands[] = [
                    'brand_id' => $brand->base_merchant_id,
                    'brand_name' => $brand->name,
                ];
            }

            $body['link_to_brands'] = $linkToBrands;

            // Add price
            $body['lowest_selling_price'] = $lowestPrice;
            $body['highest_selling_price'] = $highestPrice;

            $body['lowest_original_price'] = $lowestOriginalPrice;
            $body['highest_original_price'] = $highestOriginalPrice;

            // Add linked country
            $body['country'] = $product->country->name;

            // Update image path
            $imagePath = '';
            $imageCdn = '';
            foreach($product->media as $mainPhoto) {
                $body['image_path'] = $mainPhoto->path;
                $body['image_cdn'] = $mainPhoto->cdn_url;
            }

            // Update product tags
            $productTags = [];
            foreach($product->product_tags as $productTag) {
                $productTags[] = $productTag->product_tag;
            }

            $body['product_tags'] = $productTags;

            // Delete old document before inserting/updating new one.
            if ($response_search['hits']['total'] > 0) {
                $params = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.product_affiliations.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.product_affiliations.type'),
                    'id' => $response_search['hits']['hits'][0]['_id']
                ];

                $response = $this->poster->delete($params);
            }

            $params['body'] = $body;

            $response = $this->poster->index($params);

            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            $fakeJob = new FakeJob();
            $esQueue = new ESProductAffiliationSuggestionUpdateQueue();
            $suggestion = $esQueue->fire($fakeJob, ['product_id' => $productId]);

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s; Product ID: %s; Brand Product Name: %s',
                                $job->getJobId(),
                                $esConfig['indices']['product_affiliations']['index'],
                                $esConfig['indices']['product_affiliations']['type'],
                                $product->product_id,
                                $product->title);
            Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['product_affiliations']['index'],
                                $esConfig['indices']['product_affiliations']['type'],
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
