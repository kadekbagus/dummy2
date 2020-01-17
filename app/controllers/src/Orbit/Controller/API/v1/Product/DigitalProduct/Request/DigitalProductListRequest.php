<?php namespace Orbit\Controller\API\v1\Product\DigitalProduct\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * Digital Product List Request
 *
 * @todo  find a way to properly inject current user into request (might be a service)
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductListRequest extends ValidateRequest
{
    /**
     * Allowed roles to access this request.
     * @var array
     */
    protected $roles = ['product manager'];

    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'skip' => 'required|numeric',
            'take' => 'required|numeric',
            'sortby' => 'sometimes|in:status,product_type,product_name',
            'sortmode' => 'sometimes|in:asc,desc',
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array
     */
    public function messages()
    {
        return [];
    }
}
