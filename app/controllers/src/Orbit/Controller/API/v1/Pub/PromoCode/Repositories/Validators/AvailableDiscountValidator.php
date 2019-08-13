<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Validators;

use DiscountCode;

class AvailableDiscountValidator
{
    public function user($currentUser)
    {
        $this->currentUser = $currentUser;
        return $this;
    }

    private function __invoke($attribute, $value, $parameters, $validators)
    {
        $discount = DiscountCode::where('discount_code', $value)
            ->available()
            ->first();

        if (empty($discount)) {
            //no more promo code is available, try if current user has
            //reserved promo code
            $discount = $this->currentUser
                ->discountCodes()
                ->where('discount_code', $value)
                ->reserved()
                ->first();
        }
        return !empty($discount);
    }

}
