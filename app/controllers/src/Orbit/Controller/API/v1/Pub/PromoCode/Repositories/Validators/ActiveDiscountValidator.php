<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Validators;

use Discount;

class ActiveDiscountValidator extends AbstractValidator
{
    //actual validator. Validator::extend() cannot work with invokable
    //class eventhough can accept anonymous function, so we need to use
    public function validate($attribute, $value, $parameters, $validators)
    {
        $discount = Discount::where('discount_code', $value)
            ->active()
            ->betweenExpiryDate()
            ->first();
        return !empty($discount);
    }
}
