<?php

return [
    'subject' => 'Coupon Not Available',
    'subject_pulsa' => '[Admin] Can not Purchase Pulsa',
    'subject_pulsa_pending' => '[Admin] Purchase Pulsa PENDING',

    'header' => [
        'invoice'       => 'Notice',
        'order_number'  => 'Transaction ID: :transactionId',
        'title'         => 'Immediate Refund!'
        'title_pulsa_pending' => 'Pending MCash Pulsa',
    ],

    'body' => [
        'greeting' => 'Hello, Admin
                        <br>
                        Please contact and immediately refund the following transaction:',

        'greeting_pulsa' => 'Hello, Admin
                         <br>
                         We got a PENDING status from MCash, please check the following Pulsa transaction:',

        'help' => 'Total refund: <strong>:total</strong>',

        'thank_you' => 'Thank you and have a nice day.',
    ],

    'table_customer_info' => [
        'header' => [
            'transaction_id' => 'Transaction ID',
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
