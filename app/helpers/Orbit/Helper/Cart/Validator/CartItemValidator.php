<?php

namespace Orbit\Helper\Cart\Validator;

use BrandProductReservation;
use CartItem;
use Illuminate\Support\Facades\App;
use Order;

/**
 * Cart Item Validator.
 *
 * @author Budi <budi@gotomalls.com>
 */
class CartItemValidator
{
    public function exists($attrs, $value, $params)
    {
        $cartItem = CartItem::with(['brand_product_variant'])
            ->where('status', CartItem::STATUS_ACTIVE)
            ->where('cart_item_id', $value)
            ->where('user_id', App::make('currentUser')->user_id)
            ->first();

        if (! empty($cartItem)) {
            App::instance('cartItem', $cartItem);
            return true;
        }

        return false;
    }

    public function quantityAvailableForCartUpdate($attr, $requestedQty, $params)
    {
        if (! App::bound('cartItem')) {
            return false;
        }

        $variant = App::make('cartItem')->brand_product_variant;

        if (! empty($variant)) {
            return $variant->quantity >= $requestedQty;
        }

        return false;
    }
}
