<?php

namespace Orbit\Helper\Cart\Validator;

use BrandProductReservation;
use CartItem;
use Illuminate\Support\Facades\App;
use Order;
use PaymentTransaction;

/**
 * Order Validator.
 *
 * @author Budi <budi@gotomalls.com>
 */
class OrderValidator
{
    public function exists($attrs, $value, $params)
    {
        $order = Order::onWriteConnection()->with(['details', 'payment_detail.payment'])
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
        $usedQuantity = BrandProductReservation::getReservedQuantity($variant->brand_product_variant_id);

        // Add purchased items' count as used quantity.
        $usedQuantity += Order::getPurchasedQuantity($variant->brand_product_variant_id);

        return $variant->quantity - $usedQuantity >= $requestedQuantity;
    }

    public function canCancel($attr, $value, $params)
    {
        // If not cancellation request, then skip this rule.
        // Otherwise, if a cancellation request, continue with more checking.
        if ($value !== 'cancel') {
            return true;
        }

        // If purchase not found, then return false
        if (! App::bound('purchase')) {
            return false;
        }

        // So here we assume request is to cancel, continue with more checking.
        $purchase = App::make('purchase');

        // If not a brand product Order purchase, then skip this rule.
        if (! $purchase->forOrder()) {
            return true;
        }

        // Make sure to allow cancellation only if payment was paid.
        if ($purchase->status !== PaymentTransaction::STATUS_SUCCESS) {
            return false;
        }

        // At last, check if cancellation allowed on current Order status.
        $detail = $purchase->details->filter(function($detail) {
            return $detail->object_type === 'order';
        })->first();

        $order = Order::findOrFail($detail->object_id);

        return in_array($order->status, [
            Order::STATUS_PAID,
            Order::STATUS_READY_FOR_PICKUP,
        ]);
    }
}
