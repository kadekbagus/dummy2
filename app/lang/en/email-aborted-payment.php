<?php

return [
    'subject' => 'Gotomalls Cancelled Payment Transaction',

    'header' => [
        'email-type'       => 'Notice',
    ],

    'body' => [
        'greeting' => '<strong>Hi, :customerName!</strong>
                        <br>
                        We noticed you canceled your payment transaction on Gotomalls.com when purchasing:',

        'transaction_labels' => [
            'transaction_id' => 'Transaction ID: ',
            'transaction_date' => 'Transaction Date: ',
            'coupon_name' => 'Coupon Name: ',
            'coupon_price' => 'Coupon Price: ',
            'pulsa_name' => 'Pulsa/Data Plan: ',
            'pulsa_phone_number' => 'Phone Number: ',
            'pulsa_price' => 'Price: ',
            'coupon_quantity' => 'Quantity: ',
            'customer_name' => 'Customer Name: ',
            'email' => 'Email: ',
            'phone' => 'Phone: ',
            'total_amount' => 'Total Amount: ',
            'status' => 'Status: ',
            'status_aborted' => 'Aborted',
        ],

        'payment-info-line-1' => 'Did you find any problems trying to make the purchase?',
        'payment-info-line-2' => 'Please help us improve Gotomalls.com by replying to this email to tell us what when wrong and why you did not complete the purchase.',
        'payment-info-line-3' => 'We welcome your feedback and our Customer Service Team is always available to help.',
        'payment-info-line-4' => 'You can make a new purchase of Coupon by clicking button below.',
        'payment-info-line-4-pulsa' => 'You can make a new purchase of Pulsa by clicking button below.',
        'payment-info-line-4-data-plan' => 'You can make a new purchase of Data Plan by clicking button below.',

        'regards' => 'Thank you,<br><br>Gotomalls Service Team',

        'buttons' => [
            'buy_coupon' => 'Buy Coupon Now',
            'buy_pulsa' => 'Buy Pulsa Now',
            'buy_data_plan' => 'Buy Data Plan Now',
        ]
    ],
];
