<?php

return [
    'labels' => [
        'transaction_details' => 'Detail Transaksi',
        'transaction_id' => 'ID Transaksi',
        'transaction_date' => 'Tanggal Transaksi',

        // generics
        'billing_details' => 'Detail Tagihan',
        'billing_id' => 'ID Pelanggan',
        'billing_name' => 'Nama',
        'billing_amount' => 'Jumlah Tagihan',
        'convenience_fee' => 'Biaya Layanan',
        'total_amount' => 'Total Bayar',

        'water_bill' => [
            'periode' => 'Rek Bulan',
            'meter_start' => 'Meter Awal',
            'meter_end' => 'Meter Akhir',
            'usage' => 'Pemakaian',
            'penalty' => 'Denda',
        ],

        'pbb_tax' => [
            'periode' => 'Rek Bulan',
            'meter_start' => 'Meter Awal',
            'meter_end' => 'Meter Akhir',
            'usage' => 'Pemakaian',
            'penalty' => 'Denda',
        ],

        'bpjs_bill' => [
            'periode' => 'Rek Bulan',
            'meter_start' => 'Meter Awal',
            'meter_end' => 'Meter Akhir',
            'usage' => 'Pemakaian',
            'penalty' => 'Denda',
        ],
    ],

    'receipt' => [
        'subject' => 'Kuitansi Pembelian Dari Gotomalls.com',
    ],

    'new-order' => [
        'subject' => 'New Product Order!',
        'title' => 'New Order',
        'greeting' => 'Hello :recipientName,',
        'body' => [
            'line-1' => 'You have new product order! Please find the details below and take necessary action.',

            'help' => 'Please contact our customer service at <a style="color:#f43d3c;text-decoration:none;" href="tel::csPhone">:csPhone</a> or email at <a style="color:#f43d3c;text-decoration:none;" href="mailto::csEmail">:csEmail</a> if you find any difficulties.',
            'thank_you' => '',
        ],
    ],

    'pickup-order' => [
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

    'complete-order' => [
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
];
