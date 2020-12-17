<?php

namespace Orbit\Controller\API\v1\Product\DigitalProduct\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * Digital Product List Request
 *
 * @author Budi <budi@gotomalls.com>
 */
class ListRequest extends ValidateRequest
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
            'skip' => 'required|integer',
            'take' => 'required|integer',
            'sortby' => 'sometimes|in:status,product_type,product_name,selling_price,updated_at,provider_product',
            'sortmode' => 'sometimes|in:asc,desc',
        ];
    }
}
