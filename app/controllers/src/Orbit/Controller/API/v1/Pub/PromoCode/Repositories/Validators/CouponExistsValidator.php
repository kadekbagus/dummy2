<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Validators;

use Coupon;

class CouponExistsValidator
{

    private function __invoke($attribute, $value, $parameters, $validators)
    {
        $data = $validators->getData();
        $valid = true;
        if ($data['object_type'] === 'coupon') {
            $coupon = Coupon::where('promotion_id', $value)->active()->first();
            $valid = !empty($coupon);
        }
        return $valid;
    }

}
