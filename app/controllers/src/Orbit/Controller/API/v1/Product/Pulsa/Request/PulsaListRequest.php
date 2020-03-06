<?php

namespace Orbit\Controller\API\v1\Product\Pulsa\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * Pulsa List Request
 *
 * @author kadek <kadek@gotomalls.com>
 */
class PulsaListRequest extends ValidateRequest
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
            'object_type' => 'sometimes|in:pulsa,data_plan',
            'skip' => 'sometimes|required|min:0',
            'take' => 'sometimes|required|min:1',
            'sortby' => 'sometimes|in:pulsa_item_id,pulsa_code,pulsa_display_name,value,price,name,quantity,status',
            'sortmode' => 'sometimes|in:asc,desc',
        ];
    }
}
