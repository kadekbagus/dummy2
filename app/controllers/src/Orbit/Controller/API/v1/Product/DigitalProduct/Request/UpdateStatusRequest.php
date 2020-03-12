<?php

namespace Orbit\Controller\API\v1\Product\DigitalProduct\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * Digital Product Update Request
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductUpdateStatusRequest extends ValidateRequest
{
    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return ['id' => 'required'];
    }
}
