<?php

namespace Orbit\Queue\Elasticsearch;

use Product;
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
 * Update product affiliation suggestion on ES.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ESProductAffiliationSuggestionUpdateQueue
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
                'merchants',
            ])
            ->where('product_id', $productId)
            ->where('products.status', 'active')
            ->first();

        try {
            // check exist elasticsearch index
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.product_affiliation_suggestions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.product_affiliation_suggestions.type'),
                'body' => [
                    'query' => [
                        'match' => [
                            '_id' => $productId
                        ]
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);

            // delete suggestion document if the status inactive
            if ($response_search['hits']['total'] > 0 && empty($product)) {
                $paramsDelete = [
                    'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.product_affiliation_suggestions.index'),
                    'type' => Config::get('orbit.elasticsearch.indices.product_affiliation_suggestions.type'),
                    'id' => $productId
                ];
                $responseDelete = $this->poster->delete($paramsDelete);

                ElasticsearchErrorChecker::throwExceptionOnDocumentError($responseDelete);

                $job->delete();

                $message = sprintf('[Job ID: `%s`] Elasticsearch Delete Document in Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
                                    $job->getJobId(),
                                    $esConfig['indices']['product_affiliation_suggestions']['index'],
                                    $esConfig['indices']['product_affiliation_suggestions']['type']);
                Log::info($message);

                return [
                    'status' => 'ok',
                    'message' => $message
                ];
            } else if (empty($product)) {
                $job->delete();

                return [
                    'status' => 'fail',
                    'message' => sprintf('[Job ID: `%s`] Product Affiliation %s is not found.', $job->getJobId(), $productId)
                ];
            }

            if ($product->merchants->isEmpty()) {
                $job->delete();

                return [
                    'status' => 'fail',
                    'message' => sprintf('[Job ID: `%s`] Product Affiliation %s brand is empty.', $job->getJobId(), $productId)
                ];
            }

            $response = NULL;
            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.product_affiliation_suggestions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.product_affiliation_suggestions.type'),
                'id' => $product->product_id,
                'body' => []
            ];

            // generate input
            $strings = $product->name;
            $strings = preg_replace('/[^A-Za-z0-9 ] /', '', $strings);
            $strings = str_replace(['"', "'"], '', $strings);

            $textName = $strings;
            $explode = explode(' ', $textName);
            $count = count($explode);

            $combo = array();
            for($a = 0; $a < $count; $a++) {
                $textName = '';
                for($b = $a; $b < $count; $b++) {
                    $textName .= $explode[$b] . ' ';
                }
                $combo[] = substr($textName, 0, -1);
            }

            $brand = $product->merchants->first();

            $productCountry = $product->country->name;
            $productCity = $productCountry;

            $suggest = [
                'input'   => $combo,
                'output'  => $product->name,
                'payload' => [
                    'id' => $product->product_id,
                    'type' => 'product_affiliation',
                    'brand_name' => $brand->name,
                ],
            ];

            $esBody = [
                'product_name'  => $product->name,
                'country'    => [$productCountry],
                'city'       => [$productCity],
                'brand_id'      => $brand->base_merchant_id,
                'brand_name'    => $brand->name,
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

            $indexParams['index']  = $esPrefix . Config::get('orbit.elasticsearch.indices.product_affiliation_suggestions.index');
            $this->poster->indices()->refresh($indexParams);

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
                                $job->getJobId(),
                                $esConfig['indices']['product_affiliation_suggestions']['index'],
                                $esConfig['indices']['product_affiliation_suggestions']['type']);
            Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Elasticsearch Update Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['product_affiliation_suggestions']['index'],
                                $esConfig['indices']['product_affiliation_suggestions']['type'],
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