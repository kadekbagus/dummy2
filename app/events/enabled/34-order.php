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
                                'payment_transactions.timezone_name'
                            )
                        ->join('payment_transaction_details', function ($q) {
                                $q->on('payment_transaction_details.object_id','=','orders.order_id');
                                $q->where('payment_transaction_details.object_type', '=', 'order');
                        })
                        ->join('payment_transactions', 'payment_transactions.payment_transaction_id','=','payment_transaction_details.payment_transaction_id')
                        ->with([
                            'store' => function($q) use ($prefix) {
                                $q->addSelect(DB::raw("CONCAT({$prefix}merchants.name,' at ', om.name) as location"),'merchants.merchant_id');
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

            $bppUser = BppUser::select('name','email')->where('bpp_user_id', $bppUserId)->first();

        if ($order) {

            foreach ($order->order_details as $key => $value) {
                $order->order_details[$key]->name = $value->brand_product_variant->brand_product->product_name;
                unset($value->brand_product_variant);
                $var = null;
                foreach ($order->order_details[$key]->order_variant_details as $key3 => $value3) {
                    $var[] = $value3->value;
                }
                $order->order_details[$key]->variant = implode(",", $var);
                unset($value->order_variant_details);
            }

            // generate data for email
            $supportedLangs = ['en'];

            $store = isset($order->store->location) ? $order->store->location : null;

            $pickUpCode = isset($order->pick_up_code) ? $order->pick_up_code : null;

            $cs = Config::get('orbit.contact_information.customer_service');

            $customer = (object) ['email' => $order->email,
                                  'name'  => $order->name,
                                  'phone' => $order->phone
                                ];
                                    
            $transaction = ['orderId' => $order->order_id,
                            'total'   => $order->total_payment,
                            'items'   => $order->order_details->toArray(),
                            ];

            $format = 'd F Y, H:i';
            $transactionDateTime = isset($order->timezone_name) ? 
                                   $order->created_at->timezone($this->timezone_name)->format($format) : 
                                   $order->created_at->format($format);
            
            $localTimeZone = Order::getLocalTimezoneName($order->timezone_name);
            $transactionDateTime =  $transactionDateTime.' '.$localTimeZone;
                            
            // send email to the user
            Queue::push('Orbit\\Queue\\Order\\ReadyToPickupMailQueue', [
                'recipientEmail'      => $order->email,
                'recipientName'       => $order->name,
                'transaction'         => $transaction,
                'customer'            => $customer,
                'transactionDateTime' => $transactionDateTime,
                'emailSubject'        => trans('email-order.ready-to-pickup-order.subject', [], '', 'en'),
                'supportedLangs'      => $supportedLangs,
                'cs'                  => $cs,
                'pickUpCode'          => $pickUpCode,
                'store'               => $store,
                'type'                => 'user',
            ]);

            // send email to store admin
            if (isset($bppUser->email) && isset($bppUser->name)) {
                Queue::push('Orbit\\Queue\\Order\\ReadyToPickupMailQueue', [
                    'recipientEmail'      => $bppUser->email,
                    'recipientName'       => $bppUser->name,
                    'transaction'         => $transaction,
                    'customer'            => $customer,
                    'transactionDateTime' => $transactionDateTime,
                    'emailSubject'        => trans('email-order.ready-to-pickup-order.subject', [], '', 'en'),
                    'supportedLangs'      => $supportedLangs,
                    'cs'                  => $cs,
                    'pickUpCode'          => $pickUpCode,
                    'store'               => $store,
                    'type'                => 'admin',
                ]);
            }
        }

    }

});