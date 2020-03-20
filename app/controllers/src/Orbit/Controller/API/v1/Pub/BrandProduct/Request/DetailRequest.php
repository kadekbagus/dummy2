<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Request;

use Orbit\Controller\API\v1\BrandProduct\Validator\BrandProductValidator;
use Orbit\Helper\Request\ValidateRequest;
use Validator;

/**
 * Detail request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DetailRequest extends ValidateRequest
{
    protected $roles = ['guest', 'consumer'];

    public function rules()
    {
        return [
            'product_id' => 'required|orbit.exists.brand_product',
        ];
    }

    protected function registerCustomValidations()
    {
        Validator::extend(
            'orbit.exists.brand_product',
            BrandProductValidator::class . '@exists'
        );
    }
}
