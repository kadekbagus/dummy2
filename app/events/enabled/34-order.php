<?php
/**
 * Event listener for Order related events
 */

Event::listen('orbit.order.ready-for-pickup', function($orderId, $bppUserId)
{
    if (! empty($orderId)) {

        $prefix = DB::getTablePrefix();
        $order = Order::select('orders.order_id',
                                'orders.status',
                                'orders.total_amount as total_payment',
                                'orders.user_id',
                                'orders.merchant_id',
                                'orders.pick_up_code',
                                'payment_transactions.user_email as email',
                                'payment_transactions.user_name as name',
                                'payment_transactions.phone',
                                'payment_transactions.created_at',
                                'payment_transactions.timezone_name',
                                'payment_transactions.currency'
                            )
                        ->join('payment_transaction_details', function ($q) {
                                $q->on('payment_transaction_details.object_id','=','orders.order_id');
                                $q->where('payment_transaction_details.object_type', '=', 'order');
                        })
                        ->join('payment_transactions', 'payment_transactions.payment_transaction_id','=','payment_transaction_details.payment_transaction_id')
                        ->with([
                            'store' => function($q) use ($prefix) {
                                $q->addSelect('merchants.merchant_id', 'merchants.name as store_name', DB::raw("om.name as mall_name"));
                                $q->leftJoin(DB::raw("{$prefix}merchants as om"), function($join){
                                        $join->on(DB::raw('om.merchant_id'), '=', 'merchants.parent_id');
                                });
                            },
                            'order_details' => function($q) use ($prefix) {
                                    $q->addSelect('order_detail_id','order_id','brand_product_variant_id','sku','quantity',
                                                DB::raw("{$prefix}order_details.selling_price*{$prefix}order_details.quantity as total"));
                                    $q->with(['brand_product_variant' => function($q) use ($prefix) {
                                        $q->addSelect('brand_product_id','brand_product_variant_id');
                                        $q->with(['brand_product' => function($q) use ($prefix) {
                                            $q->addSelect('brand_product_id','product_name');
                                        }]);
                                    }, 'order_variant_details' => function($q){
                                        $q->addSelect('order_detail_id', 'variant_name', 'value');
                                    }]);
                                }
                            ])
                        ->where('orders.order_id', '=', $orderId)
                        ->first();
              
    if ($order) {              
        // bpp user that confirm ready to pickup
        $bppUser = BppUser::select('bpp_user_id','name','email')->where('bpp_user_id', $bppUserId)->first();

        // bpp user where the pickup happened
        $bppUserPickup = BppUser::select('bpp_user_id','name','email')->where('merchant_id', $order->store->merchant_id)->first();

        foreach ($order->order_details as $key => $value) {
            $order->order_details[$key]->name = $value->brand_product_variant->brand_product->product_name;
            unset($value->brand_product_variant);
            $var = null;
            foreach ($order->order_details[$key]->order_variant_details as $key3 => $value3) {
                $var[] = $value3->value;
            }
            $order->order_details[$key]->variant = implode(",", $var);
            unset($value->order_variant_details);
            $order->order_details[$key]->total = Order::formatCurrency($order->order_details[$key]->total, $order->currency);
        }

        // generate data for email
        $supportedLangs = ['en','id'];

        $storeName = isset($order->store->store_name) ? $order->store->store_name : null;
        $mallName = isset($order->store->mall_name) ? $order->store->mall_name : null;
        $pickUpCode = isset($order->pick_up_code) ? $order->pick_up_code : null;

        $cs = Config::get('orbit.contact_information.customer_service');

        $customer = (object) ['email' => $order->email,
                                'name'  => $order->name,
                                'phone' => $order->phone
                            ];
                                
        $transaction = ['orderId' => $order->order_id,
                        'total'   => Order::formatCurrency($order->total_payment, $order->currency),
                        'items'   => $order->order_details->toArray(),
                        'followUpUrl' => Config::get('orbit.shop.gtm_url').'/my/purchases/orders',
                        ];

        $format = 'd F Y, H:i';
        $transactionDateTime = isset($order->timezone_name) ? 
                                $order->created_at->timezone($order->timezone_name)->format($format) : 
                                $order->created_at->format($format);
        
        $localTimeZone = Order::getLocalTimezoneName($order->timezone_name);
        $transactionDateTime =  $transactionDateTime.' '.$localTimeZone;

        $bppUrl = Config::get('orbit.product_order.follow_up_url', 'https://bpp.gotomalls.com/#!/orders/%s');
        $bppOrderUrl = sprintf($bppUrl, $order->order_id);         
        
        $emailSubject = trans('email-order.pickup-order.subject', [], '', 'en');
                        
        // send email to the user
        Queue::push('Orbit\\Queue\\Order\\ReadyToPickupMailQueue', [
            'recipientEmail'      => $order->email,
            'recipientName'       => $order->name,
            'transaction'         => $transaction,
            'customer'            => $customer,
            'transactionDateTime' => $transactionDateTime,
            'emailSubject'        => $emailSubject,
            'supportedLangs'      => $supportedLangs,
            'cs'                  => $cs,
            'pickUpCode'          => $pickUpCode,
            'storeName'           => $storeName,
            'mallName'            => $mallName,
            'type'                => 'user',
        ]);

        $transaction['followUpUrl'] = $bppOrderUrl;

        // send email to bpp user that confirm ready to pickup
        if (isset($bppUser->email) && isset($bppUser->name)) {
            Queue::push('Orbit\\Queue\\Order\\ReadyToPickupMailQueue', [
                'recipientEmail'      => $bppUser->email,
                'recipientName'       => $bppUser->name,
                'transaction'         => $transaction,
                'customer'            => $customer,
                'transactionDateTime' => $transactionDateTime,
                'emailSubject'        => $emailSubject,
                'supportedLangs'      => $supportedLangs,
                'cs'                  => $cs,
                'pickUpCode'          => $pickUpCode,
                'storeName'           => $storeName,
                'mallName'            => $mallName,
                'type'                => 'admin',
            ]);
        }

        // send email to bpp user where the pickup happened
        if ($bppUserPickup) {
            // make sure not sending to the same user twice
            if ($bppUserPickup->bpp_user_id !== $bppUser->bpp_user_id) {
                if (isset($bppUserPickup->email) && isset($bppUserPickup->name)) {
                    Queue::push('Orbit\\Queue\\Order\\ReadyToPickupMailQueue', [
                        'recipientEmail'      => $bppUserPickup->email,
                        'recipientName'       => $bppUserPickup->name,
                        'transaction'         => $transaction,
                        'customer'            => $customer,
                        'transactionDateTime' => $transactionDateTime,
                        'emailSubject'        => $emailSubject,
                        'supportedLangs'      => $supportedLangs,
                        'cs'                  => $cs,
                        'pickUpCode'          => $pickUpCode,
                        'storeName'           => $storeName,
                        'mallName'            => $mallName,
                        'type'                => 'admin',
                    ]);
                }
            }
        }
    }

    }

});