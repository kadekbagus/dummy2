<?php

return [
    'labels' => [
        'order_details' => 'Detil Pesanan',
        'transaction_id' => 'Kode Transaksi',
        'order_id' => 'Kode Pesanan',
        'customer_name' => 'Nama Pembeli',
        'customer_email' => 'Email Pembeli',
        'customer_phone' => 'Telepon Pembeli',
        'store_location' => 'Lokasi Toko',
        'store_location_detail' => ':storeName di :mallName',
        'order_date' => 'Tanggal Pesanan',
        'expiration_date' => 'Tanggal Kadaluarsa',
        'quantity' => 'Jml',
        'total_payment' => 'Total Pembayaran',
        'status' => 'Status',
        'product_details' => 'Detil Produk',
        'product_name' => 'Nama Produk',
        'product_variant' => 'Variasi',
        'product_sku' => 'SKU',
        'product_barcode' => 'Barcode',
        'product_price' => 'Harga',
        'btn_follow_up' => 'Proses Pesanan',
        'status_detail' => [
            'pending' => 'Pending',
            'cancelled' => 'Dibatalkan',
            'accepted' => 'Dipesan',
            'declined' => 'Ditolak',
            'expired' => 'Kadaluarsa',
        ],
        'reason' => 'Alasan Penolakan',
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
        'subject' => 'Produk Siap Diambil!',
        'title' => 'Produk Siap Diambil',
        'greeting' => 'Hello :recipientName,',
        'body' => [
            'line-user' => 'Pesanan anda siap untuk diambil! Kunjungi lokasi pengambilan dan tunjukan kode berikut ke kasir toko.',
            'line-admin' => 'Ada pesanan siap untuk diambil! Berikut ini adalah detil pesanan, mohon untuk ditindak lanjuti.',
            'help' => 'Please contact our customer service at <a style="color:#f43d3c;text-decoration:none;" href="tel::csPhone">:csPhone</a> or email at <a style="color:#f43d3c;text-decoration:none;" href="mailto::csEmail">:csEmail</a> if you find any difficulties.',
            'thank_you' => '',
        ],
        'btn_follow_up' => [
            'user' => 'Go to My Purchase',
            'admin' => 'Proses Pesanan'
        ]
    ],
];
