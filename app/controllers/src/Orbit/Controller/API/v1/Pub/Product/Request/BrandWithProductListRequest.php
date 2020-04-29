<?php

namespace Orbit\Controller\API\v1\Pub\Product\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * Brand with Product Affiliation List request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandWithProductListRequest extends ValidateRequest
{
    protected $roles = ['guest', 'consumer'];

    public function rules()
    {
        return [
            'skip' => 'sometimes|integer',
            'take' => 'sometimes|integer',
        ];
    }
}
