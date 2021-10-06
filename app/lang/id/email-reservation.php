<?php

return [
    'labels' => [
        'reservation_details' => 'Detil Reservasi',
        'transaction_id' => 'Kode Reservasi',
        'user_email' => 'Email User',
        'user_id' => 'ID User',
        'user_name' => 'Nama User',
        'store_location' => 'Lokasi Toko',
        'store_location_detail' => ':storeName di :mallName',
        'reserve_date' => 'Tanggal Reservasi',
        'expiration_date' => 'Tanggal Kadaluarsa',
        'cancelled_date' => 'Tanggal Dibatalkan',
        'declined_date' => 'Tanggal Ditolak',
        'pickup_date' => 'Tanggal Pengambilan',
        'quantity' => 'Jml',
        'total_payment' => 'Total Pembayaran',
        'status' => 'Status',
        'product_details' => 'Detil Produk',
        'product_name' => 'Nama Produk',
        'product_variant' => 'Variasi',
        'product_sku' => 'SKU',
        'product_barcode' => 'Barcode',
        'product_price' => 'Harga',
        'btn_see_reservation' => 'Lihat Reservasi',
        'btn_see_my_reservation' => 'Buka Riwayat Reservasi',
        'status_detail' => [
            'pending' => 'Pending',
            'cancelled' => 'Dibatalkan',
            'accepted' => 'Dipesan',
            'declined' => 'Ditolak',
            'expired' => 'Kadaluarsa',
            'done' => 'Selesai',
        ],
        'reason' => 'Alasan Penolakan',
    ],

    'made' => [
        'subject' => 'Reservasi Produk!',
        'title' => 'Reservasi Baru',
        'greeting' => 'Halo :recipientName,',
        'body' => [
            'line-1' => 'Ada reservasi yang baru saja dibuat, mohon untuk memastikan ketersediaan produk terkait. Berikut ini adalah detil reservasi.',
            'line-2' => 'Mohon untuk segera melakukan tindak lanjut terhadap reservasi di atas.',
        ],
    ],

    'canceled' => [
        'subject' => 'Reservasi Produk Dibatalkan',
        'title' => 'Reservasi Dibatalkan',
        'greeting' => 'Halo :recipientName,',
        'body' => [
            'line-1' => 'Pelanggan baru saja membatalkan reservasi berikut ini.',
        ],
    ],

    'accepted' => [
        'subject' => 'Reservasi Diterima!',
        'title' => 'Reservasi Diterima',
        'greeting' => 'Halo :recipientName,',
        'body' => [
            'line-1' => 'Reservasi anda telah diterima oleh :storeName di :mallName.
                Silakan melakukan pembayaran dengan menunjukkan Kode Reservasi pada staff di Toko yang telah ditentukan.',
            'line-2' => 'Mohon memperhatikan tanggal kadaluarsa untuk mencegah pembatalan reservasi secara otomatis.',
        ],
    ],

    'declined' => [
        'subject' => 'Reservasi Ditolak',
        'title' => 'Reservasi Ditolak',
        'greeting' => 'Halo :recipientName,',
        'body' => [
            'line-1' => 'Kami mohon maaf karena reservasi Anda telah ditolak oleh :storeName di :mallName.',
        ],
    ],

    'expired' => [
        'subject' => 'Reservasi Kadaluarsa',
        'title' => 'Reservasi Kadaluarsa',
        'greeting' => 'Halo :recipientName,',
        'body' => [
            'line-1' => 'Sayang sekali reservasi produk Anda telah kadaluarsa pada :expirationTime. Berikut ini detil reservasi Anda.',
        ],
        'body-admin' => [
            'line-1' => 'Reservasi berikut ini baru saja kadaluarsa.',
        ],
    ],

    'done' => [
        'subject' => 'Reservasi Selesai!',
        'title' => 'Reservasi Selesai',
        'greeting' => 'Halo :recipientName,',
        'body' => [
            'line-1' => 'Terima kasih telah menyelesaikan reservasi ini dengan melakukan pickup produk di :storeName :mallName. Berikut ini detil reservasi Anda.',
            'line-2' => 'Sekali lagi terima kasih dan tetap setia menggunakan Gotomalls!',
        ],
    ],
];
