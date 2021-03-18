<?php

namespace Orbit\Controller\API\v1\BrandProduct\Product\Request;

use Orbit\Controller\API\v1\BrandProduct\Product\Validator\BrandProductValidator;
use Orbit\Controller\API\v1\BrandProduct\Validator\BrandProductValidator as ValidatorBrandProductValidator;
use Orbit\Helper\Request\ValidateRequest;
use Validator;

/**
 * Brand Product Update request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class UpdateRequest extends ValidateRequest
{
    public function rules()
    {
        return [
            'brand_product_id' => join('|', [
                'required',
                'orbit.brand_product.exists',
            ]),
            'product_name' => 'sometimes|required',
            'category_id' => 'sometimes|required',
            'status' => 'sometimes|required|in:active,inactive,deleted',
            'variants' => 'sometimes|required|orbit.brand_product.variants',
            'brand_product_variants' => join('|', [
                'required_with:variants',
                'orbit.brand_product.product_variants',
                'orbit.brand_product.selling_price_lt_original_price',
                'orbit.brand_product.can_update',
            ]),
            'brand_product_main_photo' => 'sometimes|required|image|max:1024',
            'deleted_images' => 'orbit.brand_product.main_photo',
        ];
    }

    public function messages()
    {
        return [
            'product_name.required' => 'Product Name is required.',
            'category_id.required' => 'The Category is required.',
            'orbit.brand_product.can_update' => 'You are not allowed to update the product.',
        ];
    }

    protected function registerCustomValidations()
    {
        Validator::extend(
            'orbit.brand_product.exists',
            ValidatorBrandProductValidator::class . '@exists'
        );

        Validator::extend(
            'orbit.brand_product.can_update',
            BrandProductValidator::class . '@canUpdate'
        );

        Validator::extend(
            'orbit.brand_product.variants',
            BrandProductValidator::class . '@variants'
        );

        Validator::extend(
            'orbit.brand_product.product_variants',
            BrandProductValidator::class . '@productVariants'
        );

        Validator::extend(
            'orbit.brand_product.selling_price_lt_original_price',
            BrandProductValidator::class . '@sellingPriceLowerThanOriginalPrice'
        );

        Validator::extend(
            'orbit.brand_product.main_photo',
            BrandProductValidator::class . '@mainPhoto'
        );
    }
}
