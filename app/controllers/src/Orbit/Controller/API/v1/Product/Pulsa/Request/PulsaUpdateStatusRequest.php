<?php

namespace Orbit\Controller\API\v1\Product\Pulsa\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * pulsa update status request
 *
 * @author kadek <kadek@gotomalls.com>
 */
class PulsaUpdateStatusRequest extends ValidateRequest
{
    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return ['pulsa_item_id' => 'required'];
    }
}
