<?php

namespace Orbit\Controller\API\v1\Pub\Order\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * Order purchased list request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class OrderPurchasedListRequest extends ValidateRequest
{
    protected $roles = ['consumer'];

    public function rules()
    {
        return [];
    }
}
