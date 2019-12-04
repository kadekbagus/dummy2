<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Detail\Repository;

use DB;
use Carbon\Carbon;

/**
 * class that add timezone information of current coupon detail page
 * We move to separate query to improve performance
 *
 * @author Zamroni <amroni@dominopos.com>
 */
class TimezoneRepository
{
    private function getTenantTimezone($couponId)
    {
        $prefix = DB::getTablePrefix();
        return DB::select(
            "SELECT
            ot.timezone_name
            FROM {$prefix}promotion_retailer opt
            LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
            LEFT JOIN {$prefix}merchants oms ON oms.merchant_id = om.parent_id
            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
            WHERE opt.promotion_id = ?
            ORDER BY CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) ASC
            LIMIT 1",
            [ $couponId ]
        );
    }

    public function getTenantCurrentTime($couponId)
    {
        $timezone = $this->getTenantTimezone($couponId);
        $utc = Carbon::now()->tz('UTC');
        if (empty($timezone)) {
            return $utc->format('Y-m-d H:i:s');
        } else {
            return $utc->tz($timezone[0]->timezone_name)->format('Y-m-d H:i:s');
        }
    }

    private function getCouponStarted($couponId, $couponBeginDate, $couponEndDate)
    {
        $prefix = DB::getTablePrefix();
        return DB::select("
            SELECT count(opr.retailer_id) as started
            FROM {$prefix}promotion_retailer opr
                LEFT JOIN {$prefix}merchants om ON om.merchant_id = opr.retailer_id
                LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
            WHERE opr.promotion_id = ?
            AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between ? and ?
            ",
            [$couponId, $couponBeginDate, $couponEndDate]
        );
    }

    public function addTimezoneInfo($coupon)
    {
        $timezone = $this->getTenantTimezone($coupon->promotion_id);
        if (empty($timezone)) {
            $coupon->timezone = null;
        } else {
            $coupon->timezone = $timezone[0]->timezone_name;
        }

        $started = $this->getCouponStarted($coupon->promotion_id, $coupon->begin_date, $coupon->end_date);
        if (empty($started)) {
            $coupon->is_started = 'false';
        } else {
            $coupon->is_started = ($started[0]->started > 0) ? 'true' : 'false';
        }
        return $coupon;
    }
}
