<?php

namespace Orbit\Controller\API\v1\BrandProduct\Store\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * Store List Request
 *
 * @author Budi <budi@gotomalls.com>
 */
class ListRequest extends ValidateRequest
{
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
            'sortby' => 'sometimes|in:name,created_at',
            'sortmode' => 'sometimes|in:asc,desc',
        ];
    }
}
