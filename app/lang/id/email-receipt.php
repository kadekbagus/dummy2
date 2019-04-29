<?php

return [
    'subject' => 'Kuitansi Pembelian dari Gotomalls.com',

    'header' => [
        'invoice'       => 'Kuitansi',
        'order_number'  => 'No. Transaksi: :transactionId',
    ],

    'body' => [
        'greeting' => 'Yth, :customerName
                        <br>
                        Terima kasih telah melakukan pembelian :itemName di Gotomalls.com. Pembayaran Anda telah diverifikasi oleh sistem kami. Berikut ini detail dari pembelian Anda.',

        'greeting_giftncoupon' => '
            Yth, :customerName
                        <br>
                        Terima kasih telah melakukan pembelian :itemName di Gotomalls.com. Pembayaran Anda telah diverifikasi oleh sistem kami. Berikut ini detail pembelian Anda.',

        'transaction_labels' => [
            'transaction_id' => 'No. Transaksi',
            'transaction_date' => 'Tanggal Transaksi',
            'customer_name' => 'Nama Pelanggan',
            'email' => 'Email',
            'phone' => 'No. Telp Pelanggan',
            'coupon_name' => 'Nama Kupon',
            'coupon_price' => 'Harga Kupon',
            'coupon_quantity' => 'Jumlah',
            'total_amount' => 'Total',
        ],

        'redeem' => 'Untuk melihat barang yang telah dibeli dan melakukan redeem di toko, silakan klik tombol di bawah',
        'redeem_giftncoupon' => 'Klik link berikut untuk melakukan redeem di toko',

        'help' => 'Jika menemui kendala terkait pembelian, silakan hubungi layanan bantuan kami di nomor <a style="color:#f43d3c;text-decoration:none;" href="tel::csPhone">:csPhone</a> atau surel di <a style="color:#f43d3c;text-decoration:none;" href="mailto::csEmail">:csEmail</a>.',
        'thank_you' => 'Terima kasih dan kami tunggu pembelian berikutnya!',
    ],

    'table_customer_info' => [
        'header' => [
            'customer' => 'Pelanggan',
            'phone' => 'Telp',
            'email' => 'Email',
        ],
    ],

    'table_transaction' => [
        'header' => [
            'item' => 'Item',
            'quantity' => 'Jumlah',
            'price'     => 'Harga',
            'subtotal'  => 'Subtotal',
        ],
        'footer' => [
            'total'     => 'Total',
        ]
    ],

    'buttons' => [
        'redeem' => 'Buka Dompet Saya',
        'my_purchases' => 'Buka Riwayat Pembelian',
    ],
];
