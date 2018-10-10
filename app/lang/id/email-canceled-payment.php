<?php

return [
    'subject' => 'Transaction Canceled',

    'header' => [
        'email-type'       => 'Notice',
    ],

    'body' => [
        'greeting' => 'Dear, :customerName
                        <br>
                        We would like to inform you that you already canceled the transaction. Below is the detail of your transaction: ',

        'transaction_labels' => [
            'transaction_id' => 'Transaction ID: ',
            'transaction_date' => 'Transaction Date: ',
            'coupon_name' => 'Coupon Name: ',
            'coupon_price' => 'Coupon Price: ',
            'coupon_quantity' => 'Quantity: ',
            'customer_name' => 'Customer Name: ',
            'email' => 'Email: ',
            'phone' => 'Phone: ',
            'total_amount' => 'Total Amount: ',
        ],

        'payment-info-line-1' => 'Thank you.',
    ],
];
