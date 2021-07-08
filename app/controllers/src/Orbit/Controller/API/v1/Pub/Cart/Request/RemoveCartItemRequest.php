<?php

namespace Orbit\Controller\API\v1\Pub\Cart\Request;

use Illuminate\Support\Facades\Validator;
use Orbit\Helper\Request\ValidateRequest;

/**
 * Remove cart item request validation.
 *
 * @author Budi <budi@gotomalls.com>
 */
class RemoveCartItemRequest extends ValidateRequest
{
    /**
     * @param  array $rules the validation rules.
     * @return void
     */
    public function rules()
    {
        return [
            'cart_item_id' => 'required|array',
        ];
    }
}
