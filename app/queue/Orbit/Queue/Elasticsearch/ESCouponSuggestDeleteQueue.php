<?php namespace Orbit\Queue\ElasticSearch;
/**
 * Delete Coupon Suggest Elasticsearch Per ID
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;

class ESCouponSuggestDeleteQueue
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     * @param Job $job
     * @param array $data[ 'coupon_id' => NUM // Coupon ID ]
     * @return void
     * @todo Make this testable
     */
    public function fire($job, $data)
    {
        $couponId = $data['coupon_id'];

        //Es config
        $host = Config::get('orbit.elasticsearch');
        $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');

        $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                ->setHosts($host['hosts']) // Set the hosts
                ->build();

        $fakeJob = new FakeJob();

        try {
            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.coupon_suggestions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.coupon_suggestions.type'),
                'id' => $couponId
            ];

            $response = $client->delete($params);

            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            // Safely delete the object
            $job->delete();

            return [
                'status' => 'ok',
                'message' => sprintf('[Job ID: `%s`] Elasticsearch Delete Suggest Coupon Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
                                $job->getJobId(),
                                $esConfig['indices']['coupons']['index'],
                                $esConfig['indices']['coupons']['type'])
            ];
        } catch (Exception $e) {
            // Bury the job for later inspection
            JobBurier::create($job, function($theJob) {
                // The queue driver does not support bury.
                $theJob->delete();
            })->bury();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Elasticsearch Delete Suggest Coupon Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['coupons']['index'],
                                $esConfig['indices']['coupons']['type'],
                                $e->getCode(),
                                $e->getMessage())
            ];
        }
    }

}