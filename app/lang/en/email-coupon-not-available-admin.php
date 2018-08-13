<?php 

return [
    'subject' => 'Coupon Not Available',

    'header' => [
        'invoice'       => 'Notice',
        'order_number'  => 'Transaction ID: :transactionId',
        'title'         => 'Immediate Refund!'
    ],

    'body' => [
        'greeting' => 'Hallo, Admin
                        <br>
                        Please contact and immediately refund the following transaction:',

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
