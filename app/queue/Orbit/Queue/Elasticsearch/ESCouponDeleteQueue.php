<?php namespace Orbit\Queue\Elasticsearch;
/**
 * Delete Elasticsearch index when coupon campaign status has been paused and stopped.
 *
 * @author Irianto <irianto@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use Coupon;
use DB;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;

class ESCouponDeleteQueue
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
     * @author Irianto <irianto@dominopos.com>
     * @param Job $job
     * @param array $data[
     *                    'coupon_id' => NUM // Coupon ID
     * ]
     * @return void
     * @todo Make this testable
     */
    public function fire($job, $data)
    {
        $prefix = DB::getTablePrefix();
        $couponId = $data['coupon_id'];
        $coupon = Coupon::select(
                    DB::raw("
                        {$prefix}promotions.promotion_id,
                        CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                            THEN {$prefix}campaign_status.campaign_status_name
                            ELSE (CASE WHEN {$prefix}promotions.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                            FROM {$prefix}promotion_retailer opt
                                                                                LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                                                                LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                                LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                            WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                                                        )
                            THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END)
                        END AS campaign_status,
                        COUNT({$prefix}issued_coupons.issued_coupon_id) as available,
                        {$prefix}promotions.is_visible
                    "))
                    ->join('promotion_rules', 'promotion_rules.promotion_id', '=', 'promotions.promotion_id')
                    ->join('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                    ->leftJoin('issued_coupons', function($q) {
                        $q->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id')
                            ->where('issued_coupons.status', '=', "available");
                    })
                    ->where('promotions.promotion_id', $couponId)
                    ->whereRaw("{$prefix}promotions.is_coupon = 'Y'")
                    ->whereRaw("{$prefix}promotion_rules.rule_type != 'blast_via_sms'")
                    ->havingRaw("(campaign_status in ('stopped', 'expired') or available = 0 or is_visible = 'N')")
                    ->first();

        if (! is_object($coupon)) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Coupon ID %s is not found.', $job->getJobId(), $couponId)
            ];
        }

        $esConfig = Config::get('orbit.elasticsearch');
        $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');

        try {
            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.coupons.index'),
                'type' => Config::get('orbit.elasticsearch.indices.coupons.type'),
                'id' => $coupon->promotion_id
            ];

            $response = $this->poster->delete($params);

            // Example response when document created:
            // {
            //   "found": true,
            //   "_index": "coupons",
            //   "_type": "basic",
            //   "_id": "abcs23",
            //   "_version": 2,
            //   "_shards": {
            //     "total": 2,
            //     "successful": 1,
            //     "failed": 0
            //   }
            // }
            //
            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            // Safely delete the object
            $job->delete();

            return [
                'status' => 'ok',
                'message' => sprintf('[Job ID: `%s`] Elasticsearch Delete Index; Status: OK; ES Index Name: %s; ES Index Type: %s',
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
                'message' => sprintf('[Job ID: `%s`] Elasticsearch Delete Index; Status: FAIL; ES Index Name: %s; ES Index Type: %s; Code: %s; Message: %s',
                                $job->getJobId(),
                                $esConfig['indices']['coupons']['index'],
                                $esConfig['indices']['coupons']['type'],
                                $e->getCode(),
                                $e->getMessage())
            ];
        }
    }
}
