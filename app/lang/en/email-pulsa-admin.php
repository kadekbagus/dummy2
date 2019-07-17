<?php

return [
    'subject' => 'Coupon Not Available',
    'subject_pulsa' => '[Admin] Cannot Purchase Pulsa',
    'subject_pulsa_pending' => '[Admin] Purchase Pulsa PENDING',
    'subject_pulsa_retrying' => '[Admin] Purchase Pulsa RETRY',
    'subject_pulsa_success_without_sn' => '[Admin] Pulsa SUCCESS WITHOUT SN',

    'header' => [
        'type_pulsa_success_without_sn' => 'Warning',
        'invoice'       => 'Notice',
        'order_number'  => 'Transaction ID: :transactionId',
        'title'         => 'Immediate Refund!',
        'title_pulsa_pending' => 'Pending MCash Pulsa',
        'title_pulsa_retrying' => 'Retrying MCash Pulsa Purchase',
        'title_pulsa_success_without_sn' => 'Purchase Pulsa Success Without Serial Number',
    ],

    'body' => [
        'greeting' => 'Hello, Admin
                        <br>
                        Please contact and immediately refund the following transaction:',

        'greeting_pulsa' => 'Hello, Admin
                         <br>
                         We got a PENDING status from MCash, please check the following Pulsa transaction:',

        'greeting_pulsa_retrying' => 'Hello, Admin
                         <br>
                         There\'s something wrong on MCash Server while trying to purchase Pulsa.
                         Our system <strong>will retry</strong> the purchase in a few minutes.
                         If after few tries it keeps failing, then we will mark it as a <strong>failed transaction</strong> and will inform you.
                         <br>
                         Below are the transaction details.',

        'greeting_pulsa_success_without_sn' => 'Hello, Admin
                         <br>
                         We got a success Pulsa purchase <strong>without Serial Number</strong> information in it.
                         From our experiences, most of time it will ended up as a failed purchase on MCash side.
                         <br>
                         Try contacting MCash Customer Service for more information about the purchase and then take proper action as needed.
                         Below are the transaction details.',

        'help' => 'Total refund: <strong>:total</strong>',

        'pulsa_fail_info' => '<strong>Response message from MCash API: </strong><br>:reason',

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
