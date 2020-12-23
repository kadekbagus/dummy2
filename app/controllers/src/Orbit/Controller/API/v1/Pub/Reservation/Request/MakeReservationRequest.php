<?php

namespace Orbit\Controller\API\v1\Pub\Reservation\Request;

use Illuminate\Support\Facades\Validator;
use Orbit\Helper\Request\ValidateRequest;

/**
 * Reserve request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class MakeReservationRequest extends ValidateRequest
{
    protected $roles = ['consumer'];

    public function rules()
    {
        $rules = [
            'object_type' => 'required|in:brand_product',
        ];

        switch ($this->object_type) {
            case 'brand_product':
                $rules += [
                    'object_id' => 'required|orbit.brand_product_variant.exists|orbit.brand_product.exists',
                    'quantity' => 'required|numeric|orbit.brand_product_variant.quantity_available',
                ];
                break;

            // specific rules for other type goes here...
            // case 'coupon':
                // break;

            default:
                return $this->handleValidationFails();
                break;
        }

        return $rules;
    }

    protected function registerCustomValidations()
    {
        Validator::extend(
            'orbit.brand_product_variant.exists',
            'Orbit\Controller\API\v1\BrandProduct\Validator\BrandProductValidator@variant_exists'
        );

        Validator::extend(
            'orbit.brand_product.exists',
            'Orbit\Controller\API\v1\BrandProduct\Validator\BrandProductValidator@product_exists'
        );

        Validator::extend(
            'orbit.brand_product_variant.quantity_available',
            'Orbit\Controller\API\v1\BrandProduct\Validator\BrandProductValidator@quantity_available'
        );
    }
}
