<?php namespace Orbit\Queue;
/**
 * Process queue for update related elastic search with advert data
 *
 * @author Irianto Pratama <irianto@dominopos.com>
 */

use Advert;
use Coupon;
use News;
use Tenant;
use Config;
use DB;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Log;
use Queue;
use Orbit\FakeJob;

class AdvertUpdateQueue
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Job $job
     * @param array $data [user_id => NUM]
     */
    public function fire($job, $data)
    {
        $advert_id = $data['advert_id'];
        $advert = Advert::where('status', '!=', 'deleted')
                    ->where('advert_id', $advert_id)
                    ->first();

        if (! is_object($advert)) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Advert ID %s is not found.', $job->getJobId(), $advert_id)
            ];
        }

        try {
            // update es coupon, news, and promotion
            $this->updateESCoupon($advert);
            $this->updateESPromotion($advert);
            $this->updateESStore($advert);

            // Safely delete the object
            $job->delete();

            $message = sprintf('[Job ID: `%s`] Update; Status: OK',
                                $job->getJobId());
            Log::info($message);

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Update; Status: FAIL; Code: %s; Message: %s',
                                $job->getJobId(),
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

    protected function updateESCoupon($advert) {
        $fakeJob = new FakeJob();
        // find coupon relate with advert to update ESCoupon
        // check coupon before update elasticsearch
        $prefix = DB::getTablePrefix();
        $coupons = Coupon::excludeDeleted('promotions')
                    ->select(DB::raw("
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
                        COUNT({$prefix}issued_coupons.issued_coupon_id) as available
                    "))
                    ->join('promotion_rules', 'promotion_rules.promotion_id', '=', 'promotions.promotion_id')
                    ->join('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                    ->leftJoin('issued_coupons', function($q) {
                        $q->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id')
                            ->where('issued_coupons.status', '=', "available");
                    })
                    ->join('adverts', 'adverts.link_object_id', '=', 'promotions.promotion_id')
                    ->where('adverts.advert_id', '=', $advert->advert_id)
                    ->whereRaw("{$prefix}promotions.is_coupon = 'Y'")
                    ->whereRaw("{$prefix}promotion_rules.rule_type != 'blast_via_sms'")
                    ->groupBy('promotions.promotion_id')
                    ->get();

        foreach ($coupons as $key => $coupon) {
            if ($coupon->campaign_status === 'stopped' || $coupon->campaign_status === 'expired' || $coupon->available === 0) {
                // Notify the queueing system to delete Elasticsearch document
                $esQueue = new \Orbit\Queue\Elasticsearch\ESCouponDeleteQueue();
                $response = $esQueue->fire($fakeJob, ['coupon_id' => $coupon->promotion_id]);
            } else {
                // Notify the queueing system to update Elasticsearch document
                $esQueue = new \Orbit\Queue\Elasticsearch\ESCouponUpdateQueue();
                $response = $esQueue->fire($fakeJob, ['coupon_id' => $coupon->promotion_id]);
            }
        }
    }

    protected function updateESPromotion($advert) {
        $fakeJob = new FakeJob();
        // find promotion relate with advert to update ESpromotion
        // check promotion before update elasticsearch
        $prefix = DB::getTablePrefix();
        $promotion = News::excludeDeleted('news')
                ->select(DB::raw("
                    {$prefix}news.news_id,
                    CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                    THEN {$prefix}campaign_status.campaign_status_name
                    ELSE (CASE WHEN {$prefix}news.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                    FROM {$prefix}news_merchant onm
                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                    WHERE onm.news_id = {$prefix}news.news_id)
                   THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status
                "))
                ->join('adverts', 'adverts.link_object_id', '=', 'news.news_id')
                ->where('adverts.advert_id', '=', $advert->advert_id)
                ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                ->where('news.object_type', '=', 'promotion')
                ->groupBy('news.news_id')
                ->first();

        if (is_object($promotion)) {
            if ($promotion->campaign_status === 'stopped' || $promotion->campaign_status === 'expired') {
                // Notify the queueing system to delete Elasticsearch document
                $esQueue = new \Orbit\Queue\Elasticsearch\ESPromotionDeleteQueue();
                $response = $esQueue->fire($fakeJob, ['news_id' => $promotion->news_id]);
            } else {
                // Notify the queueing system to delete Elasticsearch document
                $esQueue = new \Orbit\Queue\Elasticsearch\ESPromotionUpdateQueue();
                $response = $esQueue->fire($fakeJob, ['news_id' => $promotion->news_id]);
            }
        }
    }

    protected function updateESStore($advert) {
        $fakeJob = new FakeJob();
        // find store relate with advert to update ESstore
        // check store before update elasticsearch
        $prefix = DB::getTablePrefix();
        // checking store/tenant data for updating elasticsearch data
        $store = Tenant::select('merchants.name', DB::raw('oms.country'))
                        ->excludeDeleted('merchants')
                        ->join('adverts', 'adverts.link_object_id', '=', 'merchants.merchant_id')
                        ->join(DB::raw("(
                            select merchant_id, name, country
                            from {$prefix}merchants
                            where status = 'active'
                                and object_type = 'mall'
                            ) as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                        ->where('adverts.advert_id', '=', $advert_id)
                        ->first();

        if (is_object($store)) {
            // Notify the queueing system to delete Elasticsearch document
            $esQueue = new \Orbit\Queue\Elasticsearch\ESStoreUpdateQueue();
            $response = $esQueue->fire($fakeJob, ['name' => $store->name, 'country' => $store->country]);
        }
    }
}