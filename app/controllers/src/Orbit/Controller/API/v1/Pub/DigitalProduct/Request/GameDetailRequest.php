<?php

namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Request;

use Orbit\Helper\Request\ValidateRequest;
use Validator;

/**
 * Game detail Request
 *
 * @author Budi <budi@gotomalls.com>
 */
class GameDetailRequest extends ValidateRequest
{
    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'slug' => 'required',
            'image_variant' => 'sometimes|required',
        ];
    }
}
