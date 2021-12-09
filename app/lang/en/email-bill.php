<?php

return [
    'labels' => [
        'transaction_details' => 'Transaction Details',
        'transaction_id' => 'Transaction ID',
        'transaction_date' => 'Transaction Date',

        // generics
        'billing_details' => 'Billing Details',
        'billing_id' => 'Customer ID',
        'billing_name' => 'Name',
        'billing_amount' => 'Bill Amount',
        'convenience_fee' => 'Convenience Fee',
        'total_amount' => 'Total Payment',

        'water_bill' => [
            'periode' => 'Month/Year',
            'meter_start' => 'Start Meter',
            'meter_end' => 'End Meter',
            'usage' => 'Usage',
            'penalty' => 'Penalty',
        ],

        'pbb_tax' => [
            'periode' => 'Month/Year',
            'meter_start' => 'Start Meter',
            'meter_end' => 'End Meter',
            'usage' => 'Usage',
            'penalty' => 'Penalty',
        ],

        'bpjs_bill' => [
            'periode' => 'Month/Year',
            'usage' => 'Usage',
            'penalty' => 'Penalty',
        ],
    ],

    'new' => [
        'subject' => 'New Product Order!',
        'title' => 'New Order',
        'greeting' => 'Hello :recipientName,',
        'body' => [
            'line-1' => 'You have new product order! Please find the details below and take necessary action.',

            'help' => 'Please contact our customer service at <a style="color:#f43d3c;text-decoration:none;" href="tel::csPhone">:csPhone</a> or email at <a style="color:#f43d3c;text-decoration:none;" href="mailto::csEmail">:csEmail</a> if you find any difficulties.',
            'thank_you' => '',
        ],
    ],

    'pickup' => [
        'subject' => 'Product Ready To Pickup!',
        'title' => 'Ready To Pickup',
        'greeting' => 'Hello :recipientName,',
        'body' => [
            'line-user' => 'Your orders are ready for pickup! Go to your selected pickup store location and click the "picked up" button when arrived.',
            'line-admin' => 'You have ready for pickup order! Please find the details below and take necessary action.',
            'help' => 'Please contact our customer service at <a style="color:#f43d3c;text-decoration:none;" href="tel::csPhone">:csPhone</a> or email at <a style="color:#f43d3c;text-decoration:none;" href="mailto::csEmail">:csEmail</a> if you find any difficulties.',
            'thank_you' => '',
        ],
        'btn_follow_up' => [
            'user' => 'Go to Transaction Detail Page',
            'admin' => 'Process Order'
        ]
    ],

    'complete' => [
        'subject' => 'Order Completed!',
        'title' => 'Order Completed',
        'greeting' => 'Hello :recipientName,',
        'body' => [
            'line-user' => 'Your orders are now completed! Thank you for your purchase.',
            'line-admin' => 'This order are now completed! Please find the details below.',
            'help' => 'Please contact our customer service at <a style="color:#f43d3c;text-decoration:none;" href="tel::csPhone">:csPhone</a> or email at <a style="color:#f43d3c;text-decoration:none;" href="mailto::csEmail">:csEmail</a> if you find any difficulties.',
            'thank_you' => '',
        ],
        'btn_follow_up' => [
            'user' => 'Go to My Purchase',
            'admin' => 'Process Order'
        ]
    ],

    'receipt' => [
        'subject' => 'Your Receipt from Gotomalls.com',
    ],
];
