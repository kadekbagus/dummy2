<?php

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Orbit\Database\ObjectID;

/**
 * Order model.
 *
 * @author Budi <budi@gotomalls.com>
 */
class Order extends Eloquent
{
    use ModelStatusTrait;

    const STATUS_PENDING = 'pending';
    const STATUS_WAITING_PAYMENT = 'waiting_payment';
    const STATUS_CANCELLING = 'cancelling';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_PAID = 'paid';
    const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';
    const STATUS_DONE = 'done';
    const STATUS_DECLINED = 'declined';

    protected $primaryKey = 'order_id';

    protected $table = 'orders';

    protected $guarded = [];

    public function details()
    {
        return $this->hasMany('OrderDetail');
    }

    public function payment_detail()
    {
        return $this->hasOne(PaymentTransactionDetail::class, 'object_id', 'order_id')
            ->where('payment_transaction_details.object_type', 'order');
    }

    /**
     * Create a new Order from request object.
     *
     * @param  ValidateRequest $data - the request object
     * @return static
     */
    public static function createFromRequest($request)
    {
        $cartItems = CartItem::with([
                'brand_product_variant.brand_product.brand_product_main_photo',
                'brand_product_variant.variant_options' => function($query) {
                        $query->where('option_type', 'variant_option')
                            ->with(['option.variant']);
                    },
            ])
            ->active()
            ->whereIn('cart_item_id', $request->object_id)
            ->where('user_id', $request->user()->user_id)
            ->get();

        $orderData = [];
        $orderDetails = [];
        foreach($cartItems as $cartItem) {
            $variant = $cartItem->brand_product_variant;

            if (! isset($orderData[$cartItem->merchant_id])) {
                $orderData[$cartItem->merchant_id] = [
                    'user_id' => $request->user()->user_id,
                    'status' => self::STATUS_PENDING,
                    'total_amount' => 0,
                    'merchant_id' => $cartItem->merchant_id,
                    'brand_id' => $variant->brand_product->brand_id,
                ];
            }

            $orderData[$cartItem->merchant_id]['total_amount'] +=
                $cartItem->quantity * $variant->selling_price;

            $orderData[$cartItem->merchant_id]['cart_item_ids'][] = $cartItem->cart_item_id;

            $imgPath = null;
            $cdnUrl = null;
            if (! empty($variant->brand_product->brand_product_main_photo)) {
                if (is_object($variant->brand_product->brand_product_main_photo[0])) {
                    $imgPath = $variant->brand_product->brand_product_main_photo[0]->path;
                    $cdnUrl = $variant->brand_product->brand_product_main_photo[0]->cdn_url;
                }
            }

            $orderDetails[$cartItem->merchant_id][$variant->brand_product_variant_id] = new OrderDetail([
                'sku' => $variant->sku,
                'product_code' => $variant->product_code,
                'quantity' => $cartItem->quantity,
                'brand_product_variant_id' => $variant->brand_product_variant_id,
                'brand_product_id' => $variant->brand_product->brand_product_id,
                'original_price' => $variant->original_price,
                'selling_price' => $variant->selling_price,
                'product_name' => $variant->brand_product->product_name,
                'image_url' => $imgPath,
                'image_cdn' => $cdnUrl,
            ]);

            foreach($variant->variant_options as $variantOption) {
                $orderVariantDetails[$variant->brand_product_variant_id][] = new OrderVariantDetail([
                    'option_type' => $variantOption->option_type,
                    'option_id' => $variantOption->option_id,
                    'value' => $variantOption->option->value,
                    'variant_id' => $variantOption->option->variant_id,
                    'variant_name' => $variantOption->option->variant->variant_name,
                ]);
            }
        }

        foreach($orderData as $pickupLocation => $data) {
            $cartItemIds = implode(',', $data['cart_item_ids']);
            unset($data['cart_item_ids']);

            $orders[$pickupLocation] = Order::create($data);
            $orders[$pickupLocation]->cart_item_ids = $cartItemIds;
            $orders[$pickupLocation]->details()
                ->saveMany($orderDetails[$pickupLocation]);

            foreach($orders[$pickupLocation]->details as $detail) {
                $detail->order_variant_details()->saveMany($orderVariantDetails[$detail->brand_product_variant_id]);
            }
        }

        // Event::fire('orbit.cart.order-created', [$order]);

        return $orders;
    }

    public function store()
    {
        return $this->belongsTo(Tenant::class, 'merchant_id', 'merchant_id');
    }

    public static function requestCancel($orders)
    {
        if (is_string($orders)) {
            $orders = explode(',', $orders);
        }

        if (empty($orders)) {
            throw new Exception("Missing Order IDs when requesting cancellation!");
        }

        $order = Order::whereIn('order_id', $orders)->firstOrFail();

        if ($order->status === Order::STATUS_PAID) {
            return self::cancel($orders);
        }
        else if ($order->status === Order::STATUS_READY_FOR_PICKUP) {
            Order::whereIn('order_id', $orders)->update([
                'status' => Order::STATUS_CANCELLING,
            ]);
        }

        return $orders;
    }

    public static function cancel($orderId)
    {
        if (is_string($orderId)) {
            $orderId = explode(',', $orderId);
        }

        if (empty($orderId)) {
            throw new Exception("Missing OrderID when cancelling order!");
        }

        $orders = Order::whereIn('order_id', $orderId)->get();

        foreach($orders as $order) {
            $order->status = Order::STATUS_CANCELLED;
            $order->save();
        }

        Event::fire('orbit.order.cancelled', [$orders]);

        return $orders;
    }

    public static function markAsPaid($orders)
    {
        if (is_string($orders)) {
            $orders = explode(',', $orders);
        }

        $now = Carbon::now('UTC')->format('Y-m-d H:i:s');
        $orders = Order::with(['details.brand_product_variant'])
            ->whereIn('order_id', $orders)
            ->get();

        foreach($orders as $order) {
            $order->status = self::STATUS_PAID;
            $order->paid_at = $now;
            $order->save();

            $order->details->each(function($detail) {
                if ($detail->brand_product_variant) {
                    $detail->brand_product_variant->decrement(
                        'quantity', $detail->quantity
                    );

                    $productQty = $detail->brand_product_variant->quantity;
                    if ($productQty < 0) {
                        $detail->brand_product_variant->quantity = 0;
                        $detail->brand_product_variant->save();

                        throw new \Exception("Cannot decrease product quantity! Quantity can't be lower than 0! (Qty: {$productQty})");
                    }
                }
            });
        }

        // Event::fire('orbit.cart.order-paid', [$order]);

        return $orders;
    }

    public static function readyForPickup($orderId, $bppUserId)
    {
        $order = Order::where('order_id', $orderId)->update([
                'pick_up_code' => self::createPickUpCode(),
                'status' => self::STATUS_READY_FOR_PICKUP,
            ]);

        Event::fire('orbit.order.ready-for-pickup', [$orderId, $bppUserId]);

        return $order;
    }

    public static function done($orderId, $bppUserId)
    {
        $order = Order::where('order_id', $orderId)->update([
            'status' => self::STATUS_DONE,
        ]);

        Event::fire('orbit.order.complete', [$orderId, $bppUserId]);

        return $order;
    }

    public function order_details()
    {
        return $this->hasMany('OrderDetail', 'order_id', 'order_id');
    }

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public static function createPickUpCode()
    {
        return ObjectID::make();
    }

    public static function cancelled($orderId)
    {
        $order = Order::with(['payment_detail'])
            ->where('order_id', $orderId)
            ->firstOrFail();

        if ($order->status === self::STATUS_CANCELLING) {
            $order->status = self::STATUS_CANCELLED;
            $order->save();

            Queue::later(
                3,
                'Orbit\Queue\Order\RefundOrderQueue',
                ['paymentId' => $order->payment_detail->payment_transaction_id]
            );
        } else {
            throw new Exception("Cannot update order status.", 1);
        }

        // Event::fire('orbit.cart.order-declined', [$order]);

        return $order;
    }

    public static function declined($orderId, $reason, $userId)
    {
        // @todo: need to check the previous status
        $order = Order::with(['payment_detail'])
            ->where('order_id', $orderId)
            ->update([
                'status' => self::STATUS_DECLINED,
                'cancel_reason' => $reason,
                'declined_by' => $userId,
            ]);

        Queue::later(
            3,
            'Orbit\Queue\Order\RefundOrderQueue',
            ['paymentId' => $order->payment_detail->payment_transaction_id]
        );
        // Event::fire('orbit.cart.order-declined', [$order]);

        return $order;
    }

    public static function getPurchasedQuantity($variantId)
    {
        return Order::select('quantity')
            ->join('order_details',
                'orders.order_id', '=', 'order_details.order_id'
            )
            ->where('brand_product_variant_id', $variantId)
            ->whereIn('orders.status', [
                Order::STATUS_PAID,
                Order::STATUS_CANCELLING,
                Order::STATUS_READY_FOR_PICKUP,
                Order::STATUS_DONE,
            ])
            ->sum('quantity');
    }


   public static function getLocalTimezoneName($timezone = 'UTC')
   {
        $timezoneMapping = [
            'asia/jakarta' => 'WIB',
            'asia/makassar' => 'WITA',
            'asia/jayapura' => 'WIT',
            'utc' => 'UTC',
            '' => 'UTC',
        ];

       $timezone = strtolower($timezone);
       return isset($timezoneMapping[$timezone])
                   ? $timezoneMapping[$timezone]
                   : '';
   }

   public static function formatCurrency($amount, $currency)
   {
       return $currency . ' ' . number_format($amount, 0, ',', '.');
   }

}
