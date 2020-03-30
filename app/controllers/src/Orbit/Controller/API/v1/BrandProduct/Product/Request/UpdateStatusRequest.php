<?php

namespace Orbit\Controller\API\v1\BrandProduct\Product\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * Brand Product Update request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class UpdateStatusRequest extends ValidateRequest
{
    public function rules()
    {
        return [
            'brand_product_id' => 'required'
        ];
    }
}