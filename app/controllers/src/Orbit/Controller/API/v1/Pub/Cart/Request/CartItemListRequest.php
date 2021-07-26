<?php

namespace Orbit\Controller\API\v1\Pub\Cart\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * Cart item list request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class CartItemListRequest extends ValidateRequest
{
    protected $roles = ['consumer'];

    public function rules()
    {
        return [];
    }
}
