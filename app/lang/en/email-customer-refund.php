<?php

return [
    'subject' => 'Coupon Not Available',
    'subject_pulsa' => 'Payment Refund',
    'subject_digital_product' => 'Payment Refund',

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
                        We are having a technical problem during your pulsa/data plan purchase. Below are the details.',

        'greeting_digital_product' => [
            'customer_name' => 'Hai, :customerName',
            'body' => 'We are having a technical problem during your game voucher/electricity purchase. Below are the details.',
        ],

        'content_1' => 'Your payment was successful, but we cannot get pulsa/data plan from the Operator.',
        'content_2' => 'We are sorry for the inconvenience. We just refunded the payment to your GOPAY account. Please help confirming once you received it.',

        'content_digital_product' => [
            'line_1' => 'Your payment was successful, but we cannot get pulsa/data plan from the Operator.',
            'line_2' => 'We are sorry for the inconvenience. We just refunded the payment to your GOPAY/Shopeepay account. Please help confirming once you received it.',
        ],

        'content_1_data_plan' => 'Your payment was successful, but we cannot get data plan from the Operator.',

        'thank_you' => 'Thank you! Looking forward for your next purchase!',

        'transaction_labels' => [
            'transaction_id' => 'Transaction ID: ',
            'transaction_date' => 'Transaction Date: ',
            'phone' => 'Phone: ',
            'amount' => 'Amount: ',
            'reason' => 'Refund Reason: ',
        ],

        'cs_name' => 'Customer Service Team',
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
