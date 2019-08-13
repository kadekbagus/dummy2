<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Validators;

use Validator;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ValidatorInterface;

class PromoCodeDetailValidator implements ValidatorInterface
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
    }

    public function validate()
    {
        $promoCode = OrbitInput::post('promo_code', null);

        $this->registerCustomValidationRule();

        $validator = Validator::make(
            [
                'promo_code' => $promoCode
            ],
            [
                'promo_code' => 'required|alpha_dash|active_discount',
            ],
            [
                'promo_code.required' => 'Promo Code is required.',
                'promo_code.alpha_dash' => 'PROMO_CODE_MUST_BE_ALPHA_DASH',
                'promo_code.active_discount' => 'PROMO_CODE_NOT_ACTIVE',
                'promo_code.available_discount' => 'PROMO_CODE_NOT_AVAILABLE'
            ]
        );

        // Run the validation
        if ($validator->fails()) {
            $errorMessage = $validator->messages()->first();
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }
    }
}
