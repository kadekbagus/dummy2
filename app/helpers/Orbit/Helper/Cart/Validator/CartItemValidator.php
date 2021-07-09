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

    public function quantityAvailableForCartUpdate($attrs, $value, $params)
    {
        if (! App::bound('cartItem')) {
            return false;
        }

        $variant = App::make('cartItem')->brand_product_variant;

        if (! empty($variant)) {
            return $this->validateBrandProductQuantity($variant, $value);
        }

        return false;
    }

    private function validateBrandProductQuantity($variant, $requestedQuantity)
    {
        // Count reserved items as used quantity.
        $usedQuantity = BrandProductReservation::select('quantity')
            ->where('brand_product_variant_id', $variant->brand_product_variant_id)
            ->whereIn('status', [
                BrandProductReservation::STATUS_PENDING,
                BrandProductReservation::STATUS_ACCEPTED,
                BrandProductReservation::STATUS_DONE,
            ])
            ->sum('quantity');

        // Add purchased items' count as used quantity.
        $usedQuantity += Order::select('quantity')
            ->join('order_details',
                'orders.order_id', '=', 'order_details.order_id'
            )
            ->where('brand_product_variant_id', $variant->brand_product_variant_id)
            ->whereIn('orders.status', [Order::STATUS_PAID])
            ->sum('quantity');

        return $variant->quantity - $usedQuantity >= $requestedQuantity;
    }
}
