<?php

return [
    'subject' => 'Pengembalian Dana',
    'subject_pulsa' => 'Pengembalian Dana',
    'subject_digital_product' => 'Pengembalian Dana',

    'header' => [
        'invoice'       => 'Pemberitahuan',
        'order_number'  => 'No. Transaksi: :transactionId',
    ],

    'body' => [
        'greeting' => 'Hai, :customerName
                        <br>
                        Kami mohon maaf karena tidak dapat menyediakan Kupon yang sudah Anda beli.',

        'greeting_pulsa' => 'Hai, :customerName
                        <br>
                        Kami mengalami kendala teknis yang terjadi saat pembelian pulsa/paket data melalui Gotomalls hari ini.',

        'greeting_digital_product' => [
            'customer_name' => 'Hai, :customerName',
            'body' => 'Kami mengalami kendala teknis yang terjadi saat pembelian produk game/token data melalui Gotomalls hari ini.',
        ],

        'content_1' => 'Pembayaran Kakak telah berhasil, namun pulsa/paket data tidak diterima dari operator.',
        'content_2' => 'Kami mohon maaf atas ketidaknyamanannya.
Kami telah mengembalikan pembayaran ke GOPAY Kakak, mohon dibantu konfirmasi bila pengembalian dana telah diterima.',

        'content_digital_product' => [
            'line_1' => 'Pembayaran Kakak telah berhasil, namun produk game/token tidak diterima dari penyedia pihak ketiga.',
            'line_2' => 'Kami mohon maaf atas ketidaknyamanannya.
Kami telah mengembalikan pembayaran ke GOPAY/DANA Kakak, mohon dibantu konfirmasi bila pengembalian dana telah diterima.',
        ],

        'thank_you' => 'Semoga Kakak tetap setia menggunakan Gotomalls.com!',

        'transaction_labels' => [
            'transaction_id' => 'ID Transaksi: ',
            'transaction_date' => 'Tanggal Transaksi: ',
            'phone' => 'No. HP: ',
            'amount' => 'Jumlah: ',
            'reason' => 'Alasan Refund: ',
        ],

        'cs_name' => 'Customer Service Team',
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
