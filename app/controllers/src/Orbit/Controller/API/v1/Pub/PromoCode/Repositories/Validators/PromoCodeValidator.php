<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Validators;

use Validator;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ValidatorInterface;
use Discount;
use DiscountCode;
use Coupon;

class PromoCodeValidator implements ValidatorInterface
{
    private function registerCustomValidationRule()
    {
        Validator::extend('active_discount', function($attribute, $value, $parameters) {
            $discount = Discount::where('discount_code', $value)
                ->active()
                ->betweenExpiryDate()
                ->first();
            return !empty($discount);
        });

        Validator::extend('available_discount', function($attribute, $value, $parameters) {
            $discount = DiscountCode::where('discount_code', $value)
                ->available()
                ->first();
            return !empty($discount);
        });

        Validator::extend('coupon_exists', function($attribute, $value, $parameters, $validators) {
            $data = $validators->getData();
            $valid = true;
            if ($data['object_type'] === 'coupon') {
                $coupon = Coupon::where('promotion_id', $value)->active()->first();
                $valid = !empty($coupon);
            }
            return $valid;
        });
    }

    public function validate()
    {
        $promoCode = OrbitInput::get('promo_code', null);
        $objectId = OrbitInput::get('object_id', null);
        $objectType = OrbitInput::get('object_type', null);
        $quantity = OrbitInput::get('qty', null);

        $this->registerCustomValidationRule();

        $validator = Validator::make(
            array(
                'promo_code' => $promoCode,
                'object_id' => $objectId,
                'object_type' => $objectType,
                'quantity' => $quantity,
            ),
            array(
                'promo_code' => 'required|alpha_dash|active_discount|available_discount',
                'object_id' => 'required|alpha_dash|coupon_exists',

                //for now only accepting coupon and pulsa
                'object_type' => 'required|in:coupon,pulsa',

                'quantity' => 'required|integer|min:1',
            ),
            array(
                'promo_code.required' => 'Promo Code is required',
                'promo_code.alpha_dash' => 'Promo Code must be alpha numeric and dash and underscore characters',
                'promo_code.active_discount' => 'Promo Code must be valid not expired discount code',
                'promo_code.available_discount' => 'No more promo codes available',

                'object_id.required' => 'Object Id is required',
                'object_id.alpha_dash' => 'Object Id must be alpha numeric and dash and underscore characters',
                'object_id.coupon_exists' => 'Object Id must be Id of valid active coupon',

                'object_type.required' => 'Object Type is required',
                'object_type.in' => 'Object Type must be coupon or pulsa',

                'quantity.required' => 'Quantity is required',
                'quantity.integer' => 'Quantity must be integer value',
                'quantity.min' => 'Quantity must be at least 1',
            )
        );

        // Run the validation
        if ($validator->fails()) {
            $errorMessage = $validator->messages()->first();
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }
    }
}
