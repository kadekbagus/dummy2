<?php

use Illuminate\Support\Facades\DB;

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
    const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';
    const STATUS_DONE = 'done';

    protected $guarded = [];

    protected $primaryKey = 'order_id';

    public function details()
    {
        return $this->hasMany('OrderDetail');
    }

    public function user()
    {
        return $this->belongsTo('User');
    }

    /**
     * Create a new Order from request object.
     *
     * @param  ValidateRequest|ArrayAccess $data - the request object
     * @return static
     */
    public static function createFromRequest($request)
    {
        $cartItems = CartItem::with(['brand_product_variant.brand_product'])
            ->whereIn('cart_item_id', $request->object_id)
            ->get();

        $totalAmount = 0;
        $orderDetails = [];
        foreach($cartItems as $cartItem) {
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

        $order = Order::create([
                'user_id' => $request->user()->user_id,
                'status' => self::STATUS_PENDING,
                'total_amount' => $totalAmount,
            ]);

        $order->details()->saveMany($orderDetails);

        // Event::fire('orbit.cart.order-created', [$order]);

        return $order;
    }

    public static function requestCancel($orderId)
    {
        $order = Order::where('order_id', $orderId)->update([
                'status' => self::STATUS_CANCELLING,
            ]);

        // Event::fire('orbit.cart.order-cancelling', [$order]);

        return $order;
    }

    public static function cancel($orderId)
    {
        $order = Order::where('order_id', $orderId)->update([
                'status' => self::STATUS_CANCELLED,
            ]);

        // Event::fire('orbit.cart.order-cancelled', [$order]);

        return $order;
    }

    public static function pay($orderId)
    {
        $order = Order::with(['user'])->where('order_id', $orderId)->update([
            'status' => self::STATUS_PAID,
        ]);

        // Event::fire('orbit.cart.order-paid', [$order]);

        return $order;
    }

    public static function readyForPickup($orderId)
    {
        $order = Order::where('order_id', $orderId)->update([
                'status' => self::STATUS_READY_FOR_PICKUP,
            ]);

        // Event::fire('orbit.cart.order-ready-for-pickup', [$order]);

        return $order;
    }

    public static function done($orderId)
    {
        $order = Order::where('order_id', $orderId)->update([
            'status' => self::STATUS_DONE,
        ]);

        // Event::fire('orbit.cart.order-done', [$order]);

        return $order;
    }
}
