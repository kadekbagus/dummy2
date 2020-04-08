<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Request;

use Orbit\Helper\Request\ValidateRequest;
use Orbit\Helper\Searchable\Elasticsearch\Scrolling;

/**
 * Available Store List request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class AvailableStoreListRequest extends ValidateRequest
{
    protected $roles = ['guest', 'consumer'];

    public function rules()
    {
        return [
            'skip' => 'required|integer',
            'take' => 'required|integer',
            'available_store_keyword' => 'sometimes|required|min:3',
        ];
    }
}
