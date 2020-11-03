<?php

return [
    'subject' => 'Your Receipt from Gotomalls.com',

    'header' => [
        'invoice'       => 'Receipt',
        'order_number'  => 'Transaction ID: :transactionId',
    ],

    'body' => [
        'greeting' => 'Dear, :customerName
                        <br>
                        Thank you for purchasing :itemName in Gotomalls.com. Your payment has been verified by our system. Below is the transaction summary of your purchase',

        'greeting_giftncoupon' => '
            Dear, :customerName
                        <br>
                        Thank you for purchasing Coupon(s) in Gotomalls.com. Your payment has been verified by our system. Below are the details of your transaction
        ',

        'greeting_digital_product' => [
            'customer_name' => 'Dear, :customerName',
            'body' => 'Thank you for purchasing :itemName :gameName in Gotomalls.com. Your payment has been verified by our system. Below is the transaction summary of your purchase',
        ],

        'greeting_digital_product' => [
            'customer_name' => 'Dear, :customerName',
            'body' => 'Thank you for purchasing :itemName in Gotomalls.com. Your payment has been verified by our system. Below is the transaction summary of your purchase',
        ],

        'transaction_labels' => [
            'transaction_id' => 'Transaction ID',
            'transaction_date' => 'Transaction Date',
            'customer_name' => 'Customer Name',
            'email' => 'Email',
            'phone' => 'Customer Phone Number',
            'coupon_name' => 'Coupon Name',
            'coupon_expired_date' => 'Validity Redeem Date',
            'coupon_price' => 'Coupon Price',
            'coupon_quantity' => 'Quantity',
            'total_amount' => 'Total Amount',
        ],

        'redeem' => 'To see your purchased item and redeem/collect at the stores, please click the button below.',
        'redeem_giftncoupon' => 'Click on the link(s) below to redeem your coupon at the store',
        'view_my_purchases' => 'To see your purchased item, please click the button below:',

        'voucher_code' => 'Your Game Voucher Information:',
        'voucher_code_electricity' => 'Your Electricity Token Information:',

        'help' => 'Please contact our customer service at <a style="color:#f43d3c;text-decoration:none;" href="tel::csPhone">:csPhone</a> or email at <a style="color:#f43d3c;text-decoration:none;" href="mailto::csEmail">:csEmail</a> if you find any difficulties.',
        'thank_you' => 'Thank you and have a nice day.',
    ],

    'table_customer_info' => [
        'header' => [
            'trx_id' => 'Transaction ID',
            'customer' => 'Customer',
            'phone' => 'Phone',
            'email' => 'Email',
        ],
    ],

    'table_transaction' => [
        'header' => [
            'item' => 'Item',
            'quantity' => 'Quantity',
            'price'     => 'Price',
            'subtotal'  => 'Subtotal',
        ],
        'footer' => [
            'total'     => 'Total',
        ]
    ],

    'buttons' => [
        'redeem' => 'Go to My Wallet',
        'my_purchases' => 'Go to My Purchases',
    ],
];
