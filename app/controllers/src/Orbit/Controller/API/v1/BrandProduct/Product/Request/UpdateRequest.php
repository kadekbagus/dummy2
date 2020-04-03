<?php

namespace Orbit\Controller\API\v1\BrandProduct\Product\Request;

use Orbit\Controller\API\v1\BrandProduct\Product\Validator\BrandProductValidator;
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
            'brand_product_id' => 'required',
            'product_name' => 'sometimes|required',
            'category_id' => 'sometimes|required',
            'variants' => 'sometimes|required|orbit.brand_product.variants',
            'brand_product_variants' =>
                'sometimes|required|orbit.brand_product.product_variants',
            'brand_product_main_photo' => 'sometimes|required|image|max:1024',
        ];
    }

    protected function registerCustomValidations()
    {
        Validator::extend(
            'orbit.brand_product.variants',
            BrandProductValidator::class . '@variants'
        );

        Validator::extend(
            'orbit.brand_product.product_variants',
            BrandProductValidator::class . '@productVariants'
        );
    }
}