<?php

namespace Orbit\Queue\Elasticsearch;

use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use BrandProduct;
use DB;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;

/**
 * Delete specified Brand Product document from ES Server.
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 */
class ESBrandProductDeleteQueue
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
     * @author firmansyah <firmansyah@dominopos.com>
     * @param Job $job
     * @param array $data[
     *                    'brand_product_id' => NUM // Mall ID
     * ]
     * @return void
     * @todo Make this testable
     */
    public function fire($job, $data)
    {
        $prefix = DB::getTablePrefix();
        $brandProductId = $data['brand_product_id'];

        $brandProduct = BrandProduct::where('brand_product_id', $brandProductId)
                            ->where('status', '=', 'deleted')
                            ->first();

        if (! is_object($brandProduct)) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Brand Product ID %s is not found.', $job->getJobId(), $brandProductId)
            ];
        }

        $esConfig = Config::get('orbit.elasticsearch');
        $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');

        try {
            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.products.index'),
                'type' => Config::get('orbit.elasticsearch.indices.products.type'),
                'id' => $brandProduct->brand_product_id
            ];

            $response = $this->poster->delete($params);

            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            // Safely delete the object
            $job->delete();

            return [
                'status' => 'ok',
                'message' => sprintf('[Job ID: `%s`] Elasticsearch Delete Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
                                $job->getJobId(),
                                $esConfig['indices']['products']['index'],
                                $esConfig['indices']['products']['type'])
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
                                $esConfig['indices']['products']['index'],
                                $esConfig['indices']['products']['type'],
                                $e->getCode(),
                                $e->getMessage())
            ];
        }
    }
}