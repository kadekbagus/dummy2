<?php 

return [
    'header' => [
        'invoice'       => 'Invoice',
        'order_number'  => 'Order No. :transactionId',
    ],

    'body' => [
        'greeting' => 'Dear, :customerName
                        <br>
                        Thank you for purchasing :itemName in Gotomalls.com. Your payment has been verified by our system. Below is the transaction summary of your purchase',

        'redeem' => 'To see your purchased item and redeem/collect at the stores, please click the button below.',

        'help' => 'Please contact our customer service at :csPhone or email at <a href="mailto::csEmail">:csEmail</a> if you find any difficulties.',
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

    'buttons' => [
        'redeem' => 'Go to My Wallet',
    ],
];
