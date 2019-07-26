<?php

return [
    'subject' => 'Kupon Tidak Tersedia',
    'subject_pulsa' => 'Pulsa Tidak Tersedia',

    'header' => [
        'invoice'       => 'Pemberitahuan',
        'order_number'  => 'No. Transaksi: :transactionId',
    ],

    'body' => [
        'greeting' => 'Yth, :customerName
                        <br>
                        Kami mohon maaf karena tidak dapat menyediakan Kupon yang sudah Anda beli.',

        'greeting_pulsa' => 'Yth, :customerName
                        <br>
                        Kami mohon maaf karena tidak dapat menyediakan Pulsa yang sudah Anda beli.',

        'help' => 'Layanan Pelanggan kami akan mengembalikan dana Anda secepatnya.
        Proses pengembalian dana dilakukan maksimal dalam 24 jam selama hari kerja (Senin-Jumat). Saat hari libur atau akhir pekan, pengembalian akan dilakukan di hari kerja berikutnya.
        Apabila dana tidak diterima dalam jangka waktu tersebut, mohon hubungi Layanan Pelanggan kami melalui telepon
        <a style="color:#f43d3c;text-decoration: none;" href="tel::phone">:phone</a>
        atau surel
        <a style="text-decoration: none;color:#f43d3c;" href="mailto::email">:email</a>.',

        'thank_you' => 'Terima kasih!',
    ],

    'table_customer_info' => [
        'header' => [
            'customer' => 'Pelanggan',
            'phone' => 'No. Telp',
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
];
