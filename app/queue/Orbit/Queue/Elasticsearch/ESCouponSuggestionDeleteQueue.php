<?php namespace Orbit\Queue\Elasticsearch;
/**
 * Delete Elasticsearch index when coupon campaign status has been paused and stopped.
 *
 * @author shelgi <shelgi@dominopos.com>
 */
use Elasticsearch\ClientBuilder as ESBuilder;
use Config;
use Coupon;
use DB;
use Orbit\Helper\Elasticsearch\ElasticsearchErrorChecker;
use Orbit\Helper\Util\JobBurier;
use Exception;

class ESCouponSuggestionDeleteQueue
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
     * @author shelgi <shelgi@dominopos.com>
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
            // Delete mall level suggestion
            $params_search = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.coupon_mall_level_suggestions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.coupon_mall_level_suggestions.type'),
                'body' => [
                    'from' => 0,
                    'size' => 200,
                    'query' => [
                        'match' => [
                            'id' => $coupon->promotion_id
                        ]
                    ]
                ]
            ];

            $response_search = $this->poster->search($params_search);
            if ($response_search['hits']['total'] > 0) {
                foreach ($response_search['hits']['hits'] as $val) {
                    $paramsDelete = [
                        'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.coupon_mall_level_suggestions.index'),
                        'type' => Config::get('orbit.elasticsearch.indices.coupon_mall_level_suggestions.type'),
                        'id' =>  $val['_id']
                    ];

                    $responseDelete = $this->poster->delete($paramsDelete);
                }
            }
            $indexParamsMallLevel['index']  = $esPrefix . Config::get('orbit.elasticsearch.indices.coupon_mall_level_suggestions.index');
            $this->poster->indices()->refresh($indexParamsMallLevel);


            // Delete suggestion
            $params = [
                'index' => $esPrefix . Config::get('orbit.elasticsearch.indices.coupon_suggestions.index'),
                'type' => Config::get('orbit.elasticsearch.indices.coupon_suggestions.type'),
                'id' => $coupon->promotion_id
            ];

            $response = $this->poster->delete($params);

            // The indexing considered successful is attribute `successful` on `_shard` is more than 0.
            ElasticsearchErrorChecker::throwExceptionOnDocumentError($response);

            $indexParams['index']  = $esPrefix . Config::get('orbit.elasticsearch.indices.coupon_suggestions.index');
            $this->poster->indices()->refresh($indexParams);

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