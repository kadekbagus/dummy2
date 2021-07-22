<?php

namespace Orbit\Helper\Cart\Validator;

use BrandProductReservation;
use CartItem;
use Illuminate\Support\Facades\App;
use Order;

/**
 * Order Validator.
 *
 * @author Budi <budi@gotomalls.com>
 */
class OrderValidator
{
    public function exists($attrs, $value, $params)
    {
        $order = Order::with(['details'])
            ->where('order_id', $value)
            ->first();

        if (! empty($order)) {
            App::instance('currentOrder', $order);
            return true;
        }

        return false;
    }

    public function matchOrderUser($attrs, $value, $params)
    {
        if (! App::bound('currentOrder')) {
            return false;
        }

        if (App::make('currentUser')->user_id === App::make('currentOrder')->user_id) {
            return true;
        }

        return false;
    }

    public function canOrder($attrs, $cartItemIds, $params)
    {
        $cartItems = CartItem::with([
                'brand_product_variant.brand_product' => function($query) {
                    $query->active();
                }
            ])
            ->active()
            ->whereIn('cart_item_id', $cartItemIds)
            ->where('user_id', App::make('currentUser')->user_id)
            ->get();

        $available = 0;
        foreach($cartItems as $cartItem) {
            $variant = $cartItem->brand_product_variant;

            if ($variant
                && $this->validateBrandProductQuantity($variant, $cartItem->quantity)
            ) {
                $available++;
            }
        }

        return $available > 0 && $available === $cartItems->count();
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
            ->whereIn('orders.status', [
                Order::STATUS_WAITING_PAYMENT,
                Order::STATUS_PAID,
                Order::STATUS_CANCELLING,
                Order::STATUS_DONE
            ])
            ->sum('quantity');

        return $variant->quantity - $usedQuantity >= $requestedQuantity;
    }
}
