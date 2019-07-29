<?php

return [
    'subject' => 'Pengembalian Dana',
    'subject_pulsa' => 'Pengembalian Dana',

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
                        Kami mengalami kendala teknis yang terjadi saat pembelian pulsa melalui Gotomalls hari ini.',

        'content_1' => 'Pembayaran Kakak telah berhasil, namun pulsa tidak diterima dari operator.',
        'content_2' => 'Kami mohon maaf atas ketidaknyamanannya.
Kami telah mengembalikan pembayaran ke GOPAY Kakak, mohon dibantu konfirmasi bila pengembalian dana telah diterima.',

        'thank_you' => 'Semoga Kakak tetap setia menggunakan Gotomalls.com!',

        'transaction_labels' => [
            'transaction_id' => 'ID Transaksi: ',
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
