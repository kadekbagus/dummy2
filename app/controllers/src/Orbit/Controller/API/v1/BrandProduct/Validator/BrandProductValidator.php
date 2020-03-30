<?php

namespace Orbit\Controller\API\v1\BrandProduct\Validator;

use App;
use BrandProduct;

/**
 * Brand Product Validator.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductValidator
{
    public function exists($attribute, $productId, $params, $validator)
    {
        $brandProduct = BrandProduct::where('brand_product_id', $productId)
                            ->first();

        if (! empty($brandProduct)) {
            App::instance('brandProduct', $brandProduct);
        }

        return ! empty($brandProduct);
    }
}
