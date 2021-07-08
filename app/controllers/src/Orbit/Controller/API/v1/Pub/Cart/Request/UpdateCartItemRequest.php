<?php

namespace Orbit\Controller\API\v1\Pub\Cart\Request;

use Illuminate\Support\Facades\Validator;
use Orbit\Helper\Request\ValidateRequest;

/**
 * Add Item to Cart request validation.
 *
 * @author Budi <budi@gotomalls.com>
 */
class UpdateCartItemRequest extends ValidateRequest
{
    /**
     * @param  array $rules the validation rules.
     * @return void
     */
    public function rules()
    {
        return [
            'cart_item_id' => 'required|orbit.cart_item.exists',
            'quantity' => implode('|', [
                'required', 'numeric', 'min:0',
                'orbit.cart_item.quantity_available',
            ]),
        ];
    }

    /**
     * Register BrandProduct-specific validator.
     *
     * @return $void
     */
    protected function registerCustomValidations()
    {
        Validator::extend(
            'orbit.cart_item.exists',
            'Orbit\Helper\Cart\Validator\CartItemValidator@exists'
        );

        Validator::extend(
            'orbit.cart_item.quantity_available',
            'Orbit\Helper\Cart\Validator\CartItemValidator@quantityAvailableForCartUpdate'
        );
    }
}
