<?php

namespace Orbit\Controller\API\v1\Product\Pulsa\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * Pulsa Detail Request
 *
 * @author kadek <kadek@gotomalls.com>
 */
class PulsaDetailRequest extends ValidateRequest
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
            'pulsa_item_id' => 'required',
        ];
    }
}
