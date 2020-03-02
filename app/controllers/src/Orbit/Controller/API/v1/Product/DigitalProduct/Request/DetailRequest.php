<?php

namespace Orbit\Controller\API\v1\Product\DigitalProduct\Request;

use App;
use DigitalProduct;
use Orbit\Helper\Request\ValidateRequest;
use Validator;

/**
 * Digital Product Detail Request
 *
 * @todo  find a way to properly inject current user into request (might be a service)
 * @author Budi <budi@gotomalls.com>
 */
class DetailRequest extends ValidateRequest
{
    protected $roles = ['product manager'];

    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'id' => 'required|product_exists',
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'product_exists' => 'PRODUCT_DOES_NOT_EXISTS',
        ];
    }

    protected function registerCustomValidations()
    {
        Validator::extend('product_exists', 'Orbit\Controller\API\v1\Pub\DigitalProduct\Validator\DigitalProductValidator@exists');
    }
}
