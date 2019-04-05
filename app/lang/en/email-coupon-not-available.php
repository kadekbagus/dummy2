<?php

return [
    'subject' => 'Coupon Not Available',
    'subject_pulsa' => 'Pulsa Not Available',

    'header' => [
        'invoice'       => 'Notice',
        'order_number'  => 'Transaction ID: :transactionId',
    ],

    'body' => [
        'greeting' => 'Dear, :customerName
                        <br>
                        Sorry we could not provide the coupon you purchased. We apologize for this inconvenience.',

        'greeting_pulsa' => 'Dear, :customerName
                        <br>
                        Sorry we could not provide the pulsa you purchased. We apologize for this inconvenience.',

        'help' => 'Our Customer Service will contact shortly to refund your purchase. You can also contact our Customer Service at <a style="color:#f43d3c;text-decoration: none;" href="tel::phone">:phone</a> or email at <a style="text-decoration: none;color:#f43d3c;" href="mailto::email">:email</a> to get information about refund.',

        'thank_you' => 'Thank you and have a nice day.',
    ],

    'table_customer_info' => [
        'header' => [
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
