<?php

return [
    'subject' => 'Transaction Canceled',

    'header' => [
        'email-type'       => 'Notice',
    ],

    'body' => [
        'greeting' => 'Dear, :customerName
                        <br>
                        We confirm that your transaction has been canceled.  Below are the transaction details:',

        'transaction_labels' => [
            'transaction_id' => 'Transaction ID: ',
            'transaction_date' => 'Transaction Date: ',
            'coupon_name' => 'Coupon Name: ',
            'coupon_price' => 'Coupon Price: ',
            'pulsa_name' => 'Pulsa',
            'pulsa_phone_number' => 'Phone Number',
            'pulsa_price' => 'Price',
            'coupon_quantity' => 'Quantity: ',
            'customer_name' => 'Customer Name: ',
            'email' => 'Email: ',
            'phone' => 'Phone: ',
            'total_amount' => 'Total Amount: ',
            'status' => 'Status: ',
            'status_canceled' => 'Canceled',
        ],

        'payment-info-line-1' => 'Thank you.',
    ],
];
