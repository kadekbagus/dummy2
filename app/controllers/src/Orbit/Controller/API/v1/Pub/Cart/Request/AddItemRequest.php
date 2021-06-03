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

    /**
     * @override
     * @return array the final rules for request validation.
     */
    public function rules()
    {
        $rules = [
            'object_type' => 'required|in:brand_product',
        ];

        switch ($this->object_type) {
            case 'brand_product':
                $this->applyBrandProductRules($rules);
                break;

            // specific rules for other type goes here...
            // case 'coupon':
                // break;

            default:
                break;
        }

        return $rules;
    }

    /**
     * @param  array $rules the validation rules.
     * @return void
     */
    private function applyBrandProductRules(&$rules)
    {
        $rules = [
            'object_id' => implode('|', [
                'required',
                'orbit.brand_product_variant.exists',
                'orbit.brand_product.exists',
            ]),
            'pickup_location' => implode('|', [
                'required',
                'orbit.brand_product_variant.pickup_location_valid',
            ]),
            'quantity' => implode('|', [
                'required',
                'numeric',
                'orbit.brand_product_variant.quantity_available',
            ]),
        ];
    }

    /**
     * @return void
     */
    protected function registerCustomValidations()
    {
        switch ($this->object_type) {
            case 'brand_product':
                $this->registerBrandProductValidations();
                break;

            default:
                // code...
                break;
        }
    }

    /**
     * Register BrandProduct-specific validator.
     *
     * @return $void
     */
    private function registerBrandProductValidations()
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
            'orbit.brand_product_variant.pickup_location_valid',
            'Orbit\Controller\API\v1\BrandProduct\Validator\BrandProductValidator@pickupLocationValid'
        );

        Validator::extend(
            'orbit.brand_product_variant.quantity_available',
            'Orbit\Controller\API\v1\BrandProduct\Validator\BrandProductValidator@quantity_available'
        );
    }
}
