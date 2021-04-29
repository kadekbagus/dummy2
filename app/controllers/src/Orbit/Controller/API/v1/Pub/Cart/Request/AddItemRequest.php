<?php

namespace Orbit\Controller\API\v1\Pub\Cart\Request;

use Illuminate\Support\Facades\Validator;
use Orbit\Helper\Request\ValidateRequest;

/**
 * Add Item to Cart request validation.
 *
 * @author Budi <budi@gotomalls.com>
 */
class AddItemRequest extends ValidateRequest
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
                    'pickup_location' => 'required|orbit.brand_product_variant.pickup_location_valid',
                    'quantity' => 'required|numeric|orbit.brand_product_variant.quantity_available_on_pickup_location',
                ];
                break;

            // specific rules for other type goes here...
            // case 'coupon':
                // break;

            default:
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

        Validator::extend(
            'orbit.brand_product_variant.quantity_available_on_pickup_location',
            'Orbit\Controller\API\v1\BrandProduct\Validator\BrandProductValidator@quantityAvailableOnPickupLocation'
        );
    }
}
