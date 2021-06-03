<?php

namespace Orbit\Controller\API\v1\Pub\Cart\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * List request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ListRequest extends ValidateRequest
{
    protected $roles = ['consumer'];

    public function rules()
    {
        return [];
    }
}
