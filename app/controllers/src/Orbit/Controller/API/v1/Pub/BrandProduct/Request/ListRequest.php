<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Request;

use Orbit\Helper\Request\ValidateRequest;
use Orbit\Helper\Searchable\Elasticsearch\Scrolling;

/**
 * List request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ListRequest extends ValidateRequest
{
    // Enable ES scrolling support for this listing request.
    use Scrolling;

    protected $roles = ['guest', 'consumer'];

    public function rules()
    {
        return [
            'skip' => 'sometimes|integer',
            'take' => 'sometimes|integer',
            'category_id' => 'sometimes|array',
            'cities' => 'sometimes|array',
            'store_id' => 'sometimes',
            'except_id' => 'sometimes|required',
        ];
    }

    public function isSuggestion()
    {
        return $this->has('except_id');
    }
}
