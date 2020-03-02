<?php

namespace Orbit\Controller\API\v1\Product\Pulsa\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * Telco Operator Detail Request
 *
 * @author Budi <budi@gotomalls.com>
 */
class TelcoDetailRequest extends ValidateRequest
{
    protected $roles = ['product manager'];

    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'telco_operator_id' => 'required',
        ];
    }
}
