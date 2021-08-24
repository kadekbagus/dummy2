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
                'brand_product_variant.brand_product',
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

            $orderDetails[$cartItem->merchant_id][$variant->brand_product_variant_id] = new OrderDetail([
                'sku' => $variant->sku,
                'product_code' => $variant->product_code,
                'quantity' => $cartItem->quantity,
                'brand_product_variant_id' => $variant->brand_product_variant_id,
                'original_price' => $variant->original_price,
                'selling_price' => $variant->selling_price,
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

    public static function markAsPaid($orders)
    {
        if ($orders instanceof Collection) {
            $orders->update(['status' => self::STATUS_PAID]);

            return $orders;
        }

        if (! is_array($orders)) {
            $orders = [$orders];
        }

        $now = Carbon::now('UTC')->format('Y-m-d H:i:s');
        $orders = Order::with(['user'])->whereIn('order_id', $orders)->update([
            'status' => self::STATUS_PAID,
            'paid_at' => $now,
        ]);

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

    public static function done($orderId)
    {
        $order = Order::where('order_id', $orderId)->update([
            'status' => self::STATUS_DONE,
        ]);

        // Event::fire('orbit.cart.order-done', [$order]);

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

    public static function declined($orderId, $reason, $userId)
    {
        $order = Order::where('order_id', $orderId)->update([
                'status' => self::STATUS_DECLINED,
                'cancel_reason' => $reason,
                'declined_by' => $userId,
            ]);

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

}
