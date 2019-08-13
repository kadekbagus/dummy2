<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Validators;

use Validator;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ValidatorInterface;
use Discount;
use DiscountCode;
use Coupon;
use Pulsa;

class PromoCodeValidator implements ValidatorInterface
{
    private $currentUser;

    public function user($currentUser)
    {
        $this->currentUser = $currentUser;
        return $this;
    }

    private function registerCustomValidationRule()
    {
        Validator::extend('active_discount', new ActiveDiscountValidator());

        Validator::extend('available_discount', (new AvailableDiscountValidator())->user($this->currentUser));

        Validator::extend('coupon_exists', new CouponExistsValidator());

        Validator::extend('pulsa_exists', new PulsaExistsValidator());
    }

    public function validate()
    {
        $promoCode = OrbitInput::post('promo_code', null);
        $objectId = OrbitInput::post('object_id', null);
        $objectType = OrbitInput::post('object_type', null);
        $quantity = OrbitInput::post('qty', null);

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
                'object_id' => 'required|alpha_dash|coupon_exists|pulsa_exists',

                //for now only accepting coupon and pulsa
                'object_type' => 'required|in:coupon,pulsa',

                'quantity' => 'required|integer|min:1',
            ),
            array(
                'promo_code.required' => 'Promo Code is required.',
                'promo_code.alpha_dash' => 'PROMO_CODE_MUST_BE_ALPHA_DASH',
                'promo_code.active_discount' => 'PROMO_CODE_NOT_ACTIVE',
                'promo_code.available_discount' => 'PROMO_CODE_NOT_AVAILABLE',

                'object_id.required' => 'Object Id is required',
                'object_id.alpha_dash' => 'Object Id must be alpha numeric and dash and underscore characters',
                'object_id.coupon_exists' => 'Object Id must be Id of valid active coupon',
                'object_id.pulsa_exists' => 'Object Id must be Id of valid active pulsa',

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
