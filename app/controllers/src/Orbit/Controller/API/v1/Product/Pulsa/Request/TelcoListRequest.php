<?php

namespace Orbit\Controller\API\v1\Product\Pulsa\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * Telco Operator List Request
 *
 * @author Budi <budi@gotomalls.com>
 */
class TelcoListRequest extends ValidateRequest
{
    /**
     * Allowed roles to access this request.
     * @var array
     */
    protected $roles = ['product manager'];

    protected $paginationConfig = 'orbit.pagination.telco';

    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'skip' => 'sometimes|required|min:0',
            'take' => 'sometimes|required|min:1',
            'sortby' => 'sometimes|in:name,country_name,status,updated_at',
            'sortmode' => 'sometimes|in:asc,desc',
        ];
    }
}
