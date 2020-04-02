<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * Brand with Product List request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandWithProductListRequest extends ValidateRequest
{
    protected $roles = ['guest', 'consumer'];

    public function rules()
    {
        return [
            'skip' => 'required|integer',
            'take' => 'required|integer',
        ];
    }
}
