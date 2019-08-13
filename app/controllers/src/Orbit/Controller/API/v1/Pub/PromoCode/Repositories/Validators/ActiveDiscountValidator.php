<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Validators;

use Discount;

class ActiveDiscountValidator
{

    private function __invoke($attribute, $value, $parameters, $validators)
    {
        $discount = Discount::where('discount_code', $value)
            ->active()
            ->betweenExpiryDate()
            ->first();
        return !empty($discount);
    }

}
