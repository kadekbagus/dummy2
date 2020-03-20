<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * Reserve request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReserveRequest extends ValidateRequest
{
    protected $roles = ['guest', 'consumer'];

    public function rules()
    {
        return [
            'skip' => 'required|integer',
            'take' => 'required|integer',
            'category_id' => 'sometimes|array',
            'cities' => 'sometimes|array',
        ];
    }
}
