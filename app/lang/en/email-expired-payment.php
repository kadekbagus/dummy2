<?php

return [
    'subject' => 'Gotomalls Expired Payment Transaction',

    'header' => [
        'email-type'       => 'Expired Order',
    ],

    'body' => [
        'greeting' => '<strong>Hi, :customerName</strong>
                        <br>
                        We noticed your payment transaction on Gotomalls.com was not completed:',

        'greeting_digital_product' => [
            'customer_name' => '<strong>Hi, :customerName</strong>',
            'body' => 'We noticed your payment transaction on Gotomalls.com was not completed:',
        ],

        'transaction_labels' => [
            'transaction_id' => 'Transaction ID: ',
            'transaction_date' => 'Transaction Date: ',
            'coupon_name' => 'Coupon Name: ',
            'coupon_price' => 'Coupon Price: ',
            'pulsa_name' => 'Pulsa/Data Plan: ',
            'pulsa_phone_number' => 'Phone Number: ',
            'pulsa_price' => 'Price: ',
            'product_name' => 'Product: ',
            'coupon_quantity' => 'Quantity: ',
            'customer_name' => 'Customer Name: ',
            'email' => 'Email: ',
            'phone' => 'Phone: ',
            'total_amount' => 'Total Amount: ',
            'status' => 'Status: ',
            'status_expired' => 'Expired',
        ],

        'payment-info-line-1' => 'Did you find any problems trying to make the purchase?',
        'payment-info-line-2' => 'Please help us improve Gotomalls.com by replying to this email to tell us what when wrong and why you did not complete the purchase.',
        'payment-info-line-3' => 'We welcome your feedback and our Customer Service Team is always available to help.',
        'payment-info-line-4' => 'You can make a new purchase of Coupon by clicking button below.',
        'payment-info-line-4-pulsa' => 'You can make a new purchase of Pulsa by clicking button below.',
        'payment-info-line-4-data-plan' => 'You can make a new purchase of Data Plan by clicking button below.',
        'payment-info-line-4-digital-product' => 'You can make a new purchase of :productType by clicking button below.',

        'regards' => 'Thank you,<br><br>Gotomalls Service Team',

        'buttons' => [
            'buy_coupon' => 'Buy Coupon Now',
            'buy_pulsa' => 'Buy Pulsa Now',
            'buy_data_plan' => 'Buy Data Plan Now',
            'buy_digital_product' => 'Buy :productType Now',
        ]
    ],
];
