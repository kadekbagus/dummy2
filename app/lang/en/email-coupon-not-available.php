<?php

return [
    'subject' => 'Coupon Not Available',
    'subject_pulsa' => 'Pulsa Not Available',
    'subject_data_plan' => 'Data Plan Not Available',

    'header' => [
        'invoice'       => 'Notice',
        'email-type'    => 'Notice',
        'order_number'  => 'Transaction ID: :transactionId',
    ],

    'body' => [
        'greeting' => 'Dear, :customerName
                        <br>
                        Sorry we could not provide the coupon you purchased. We apologize for this inconvenience.',

        'greeting_pulsa' => 'Dear, :customerName
                        <br>
                        Sorry we could not provide the pulsa you purchased. We apologize for this inconvenience.',

        'greeting_data_plan' => 'Dear, :customerName
                        <br>
                        Sorry we could not provide Data Plan you purchased. We apologize for this inconvenience.',

        'greeting_digital_product' => [
            'customer_name' => 'Dear, :customerName',
            'body' => 'Sorry we could not provide :productType you purchased. We apologize for this inconvenience.',
        ],

        'help' => 'Our Customer Service will refund your purchase shortly. The refund will be done within 24 hours maximum during business days (Mon-Fri). For holidays and weekends, the refund will be done on the next business day. If you do not receive your refund in this period, please contact our Customer Service at <a style="color:#f43d3c;text-decoration: none;" href="tel::phone">:phone</a> or email at <a style="text-decoration: none;color:#f43d3c;" href="mailto::email">:email</a>.',

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
];
