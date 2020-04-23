<?php

namespace Orbit\Controller\API\v1\Pub\Product\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * Detail request.
 *
 * @author kadek <kadek@gotomalls.com>
 */
class DetailRequest extends ValidateRequest
{
    protected $roles = ['guest', 'consumer'];

    public function rules()
    {
        return [
            'product_id' => 'required',
        ];
    }
}
