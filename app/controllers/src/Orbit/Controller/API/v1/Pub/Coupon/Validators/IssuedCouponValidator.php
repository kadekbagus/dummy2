<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Validators;

use App;
use IssuedCoupon;

/**
 * List of custom validator related to Issued Coupon.
 *
 * @author Budi <budi@gotomalls.com>
 */
class IssuedCouponValidator
{
    public function exists($attributes, $issuedCouponId, $parameters, $validator)
    {
        $issuedCoupon = IssuedCoupon::where('issued_coupon_id', $issuedCouponId)->first();

        if (! empty($issuedCoupon)) {
            App::instance('issuedCoupon', $issuedCoupon);
        }
        else {
            App::instance('issuedCoupon', null);
        }

        return ! empty($issuedCoupon);
    }
}
