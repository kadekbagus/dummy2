<?php

use Orbit\Helper\Request\ValidateRequest;

/**
 * Order model.
 *
 * @author Budi <budi@gotomalls.com>
 */
class Order extends Eloquent
{
    const STATUS_PENDING = 'pending';
    const STATUS_CANCELLING = 'cancelling';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_PAID = 'paid';

    protected $guarded = [];

    /**
     * Create a new Order from request object.
     *
     * @param  ValidateRequest|ArrayAccess $data - the request object
     * @return static
     */
    public static function createFrom($request)
    {
        if ($request instanceof ValidateRequest) {
            $cartItems = CartItem::with(['brand_product_variant.brand_product'])
                ->whereIn('cart_item_id', $request->cart_item_id)
                ->get();

            $totalAmount = 0;
            $orderDetails = [];
            foreach($cartItems as $cartItem) {
                if (empty($cartItem->brand_product_variant)) {
                    continue;
                }

                if (empty($cartItem->brand_product_variant->brand_product)) {
                    continue;
                }

                $product = $cartItem->brand_product_variant;

                $totalAmount += $product->selling_price * $cartItem->quantity;
                $orderDetails[] = new OrderDetail([
                    'sku' => $product->sku,
                    'product_code' => $product->product_code,
                    'quantity' => $cartItem->quantity,
                    'brand_id' => $product->brand_product->brand_id,
                    'merchant_id' => $cartItem->merchant_id,
                    'original_price' => $product->original_price,
                    'selling_price' => $product->selling_price,
                ]);
            }

            return Order::create([
                'user_id' => $request->user()->user_id,
                'status' => self::STATUS_PENDING,
                'total_amount' => $totalAmount,
            ])->details()->saveMany($orderDetails);
        }

        return null;
    }

    public static function cancel($orderId)
    {
        return DB::transaction(function() use ($orderId) {
            return Order::where('order_id', $orderId)->update([
                'status' => self::STATUS_CANCELLING,
            ]);
        });
    }

    public function confirmCancel($orderId)
    {
        return DB::transaction(function() use ($orderId) {
            return Order::where('order_id', $orderId)->update([
                'status' => self::STATUS_CANCELLED,
            ]);
        });
    }
}
