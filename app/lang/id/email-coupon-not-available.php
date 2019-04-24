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

        'help' => 'Layanan Pelanggan kami akan menghubungi Anda secepatnya untuk mengurus perihal Pengembalian Dana (Refund).
        Anda juga dapat menghubungi Layanan Pelanggan kami melalui telepon
        <a style="color:#f43d3c;text-decoration: none;" href="tel::phone">:phone</a>
        atau surel
        <a style="text-decoration: none;color:#f43d3c;" href="mailto::email">:email</a>
        perihal Pengembalian Dana (Refund).',

        'thank_you' => 'Thank you and have a nice day.',
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
