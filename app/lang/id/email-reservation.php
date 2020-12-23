<?php

return [
    'labels' => [
        'reservation_details' => 'Detil Reservasi',
        'transaction_id' => 'Kode Transaksi',
        'user_email' => 'Email User',
        'store_location' => 'Lokasi Toko',
        'store_location_detail' => ':storeName di :mallName',
        'reserve_date' => 'Tanggal Reservasi',
        'expiration_date' => 'Tanggal Kadaluarsa',
        'quantity' => 'Jumlah',
        'total_payment' => 'Total Pembayaran',
        'status' => 'Status',
        'product_details' => 'Detil Produk',
        'product_name' => 'Nama Produk',
        'product_variant' => 'Variasi Produk',
        'product_sku' => 'No. SKU',
        'product_barcode' => 'Barcode Produk',
        'btn_accept' => 'Terima',
        'btn_decline' => 'Tolak',
        'status' => [
            'pending' => 'Pending',
            'cancelled' => 'Dibatalkan',
            'accepted' => 'Diterima',
            'declined' => 'Ditolak',
        ],
        'reason' => 'Alasan Penolakan',
    ],

    'made' => [
        'subject' => 'New Product Reservation!',
        'greeting' => 'Hello Admin,',
        'body' => [
            'line-1' => 'New reservation has been created.
                Please make sure the product is available and in accordance with the reservation.',
            'line-2' => 'Please confirm the reservation immediately.',
        ],
    ],

    'canceled' => [
        'subject' => 'Product Reservation Canceled',
        'greeting' => 'Hello Admin,',
        'body' => [
            'line-1' => 'Customer just canceled following reservation.',
        ],
    ],

    'accepted' => [
        'subject' => 'Reservasi Diterima!',
        'greeting' => 'Halo :customerName,',
        'body' => [
            'line-1' => 'Reservasi anda telah diterima oleh :storeName di :mallName.
                Silakan melakukan pembayaran dengan menunjukkan Kode Transaksi pada staff di Toko yang telah ditentukan.',
            'line-2' => 'Mohon memperhatikan tanggal kadaluarsa untuk mencegah pembatalan reservasi secara otomatis.',
        ],
    ],

    'declined' => [
        'subject' => 'Reservasi Ditolak',
        'greeting' => 'Halo :customerName,',
        'body' => [
            'line-1' => 'Kami mohon maaf karena reservasi Anda telah ditolak oleh :storeName di :mallName.',
        ],
    ],
];
