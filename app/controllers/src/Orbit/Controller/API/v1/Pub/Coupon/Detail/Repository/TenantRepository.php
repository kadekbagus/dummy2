<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Detail\Repository;

use Carbon\Carbon;
use IssuedCoupon;

/**
 * class that add tenant info such as mall id or country to
 * coupon detail page
 *
 * @author Zamroni <amroni@dominopos.com>
 */
class TenantRepository
{
    private function getCountryAndMallId($couponId)
    {
        $prefix = DB::getTablePrefix();
        return DB::select("
            SELECT
                m.country
                CASE
                    WHEN m.object_type = 'tenant'
                THEN
                    m.parent_id
                ELSE
                    m.merchant_id
                END as mall_id
            FROM {$prefix}promotion_retailer opr
            LEFT JOIN {$prefix}merchants m ON m.merchant_id = opr.retailer_id
            WHERE opr.promotion_id = ?",
            [$couponId]
        );

    }

    public function addTenantInfo($coupon, $user)
    {
        $tenantInfo = $this->getCountryAndMallId($coupon->promotion_id);
        if (empty($tenantInfo)) {
            $coupon->coupon_country = null;
            $coupon->mall_id = null;
        } else {
            $coupon->coupon_country = $tenantInfo[0]->country;
            $coupon->mall_id = $tenantInfo[0]->mall_id;
        }
        return $coupon;
    }

}
