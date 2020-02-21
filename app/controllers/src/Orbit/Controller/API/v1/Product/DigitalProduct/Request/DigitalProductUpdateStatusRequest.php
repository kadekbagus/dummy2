<?php namespace Orbit\Controller\API\v1\Product\DigitalProduct\Request;

use App;
use DigitalProduct;
use Orbit\Helper\Request\ValidateRequest;
use Validator;

/**
 * Digital Product Update Request
 *
 * @todo  find a way to properly inject current user into request (might be a service)
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductUpdateStatusRequest extends DigitalProductNewRequest
{
    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return array_merge(['id' => 'required']);
    }

    /**
     * Get custom validation messages.
     *
     * @return array
     */
    public function messages()
    {
        return array_merge(parent::messages());
    }

    protected function registerCustomValidations()
    {
        parent::registerCustomValidations();
    }
}
