<?php

return [
    'subject' => 'Coupon Not Available',
    'subject_pulsa' => '[Admin] Cannot Purchase Pulsa/Data Plan',
    'subject_digital_product' => '[Admin] Cannot Purchase :productType',
    'subject_pulsa_pending' => '[Admin] Purchase Pulsa/Data Plan PENDING',
    'subject_pulsa_retrying' => '[Admin] Purchase Pulsa/Data Plan RETRY',
    'subject_digital_product_retrying' => '[Admin] Purchase :productType RETRY',

    'header' => [
        'invoice'       => 'Notice',
        'order_number'  => 'Transaction ID: :transactionId',
        'title'         => 'Immediate Refund!',
        'title_pulsa_pending' => 'Pending MCash Pulsa/Data Plan',
        'title_pulsa_retrying' => 'Retrying MCash Pulsa/Data Plan Purchase',
        'title_digital_product_retrying' => 'Retrying :productType Purchase',
    ],

    'body' => [
        'greeting' => 'Hello, Admin
                        <br>
                        Please contact and immediately refund the following transaction:',

        'greeting_pulsa' => 'Hello, Admin
                         <br>
                         We got a PENDING status from MCash, please check the following Pulsa/Data Plan transaction:',

        'greeting_pulsa_retrying' => 'Hello, Admin
                         <br>
                         There\'s something wrong on MCash Server while trying to purchase Pulsa/Data Plan.
                         Our system <strong>will retry</strong> the purchase in a few minutes.
                         If after few tries it keeps failing, then we will mark it as a <strong>failed transaction</strong> and will inform you.
                         <br>
                         Below are the transaction details.',

        'greeting_digital_product_retrying' => 'Hello, Admin
                         <br>
                         There\'s something wrong on :providerName Server while trying to purchase :productType.
                         Our system <strong>will retry</strong> the purchase in a few minutes.
                         If after few tries it keeps failing, then we will mark it as a <strong>failed transaction</strong> and will inform you.
                         <br>
                         Below are the transaction details.',

        'help' => 'Total refund: <strong>:total</strong>',

        'pulsa_fail_info' => '<strong>Response message from MCash API: </strong><br>:reason',

        'digital_product_fail_info' => '<strong>Response message from :providerName : </strong><br>:reason',

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
