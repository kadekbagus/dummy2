<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Validators;

use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Validator;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ValidatorInterface;

class PromoCodeValidator implements ValidatorInterface
{
    public function validate()
    {
        $promoCode = OrbitInput::get('promo_code', null);
        $objectId = OrbitInput::get('object_id', null);
        $objectType = OrbitInput::get('object_type', null);
        $objectType = OrbitInput::get('object_type', null);

        $validator = Validator::make(
            array(
                'promo_code' => $promoCode,
                'object_id' => $objectId,
                'object_type' => $objectType,
            ),
            array(
                'promo_code' => 'required | alpha_dash | exists:discounts,discount_code',
                'object_id' => 'required | alphanum_dash',
                'object_type' => 'required | alpha_dash',
            ),
            array(
                'promo_code.required' => 'Promo Code is required',
                'promo_code.alpha_dash' => 'Promo Code must be alpha numeric and dash and underscore characters',

                'object_id.required' => 'Object Id is required',
                'object_id.alpha_dash' => 'Object Id must be alpha numeric and dash and underscore characters',

                'object_type.required' => 'Object Type is required',
                'object_type.alpha_dash' => 'Object Type must be alpha numeric and dash and underscore characters',
            )
        );

        // Run the validation
        if ($validator->fails()) {
            $errorMessage = $validator->messages()->first();
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }
    }
}
