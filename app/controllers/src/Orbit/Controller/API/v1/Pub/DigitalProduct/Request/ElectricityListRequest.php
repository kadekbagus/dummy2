<?php

namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * Electricity List Request
 *
 * @author kadek <kadek@gotomalls.com>
 */
class ElectricityListRequest extends ValidateRequest
{
    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'skip' => 'integer',
            'take' => 'integer',
            'sortby' => 'in:selling_price',
            'sortmode' => 'in:asc,desc',
        ];
    }
}