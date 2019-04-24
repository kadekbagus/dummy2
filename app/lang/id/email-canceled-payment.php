<?php

return [
    'subject' => 'Transaksi Dibatalkan',

    'header' => [
        'email-type'       => 'Pemberitahuan',
    ],

    'body' => [
        'greeting' => 'Yth, :customerName
                        <br>
                        Melalui email ini kami memberitahukan bahwa transaksi Anda telah dibatalkan. Berikut ini detail transaksi tersebut.',

        'transaction_labels' => [
            'transaction_id' => 'No. Transaksi: ',
            'transaction_date' => 'Tanggal Transaksi: ',
            'coupon_name' => 'Nama Kupon: ',
            'coupon_price' => 'Harga Kupon: ',
            'pulsa_name' => 'Pulsa',
            'pulsa_phone_number' => 'No. HP Pulsa',
            'pulsa_price' => 'Harga Pulsa',
            'coupon_quantity' => 'Jumlah: ',
            'customer_name' => 'Nama Pelanggan: ',
            'email' => 'Email: ',
            'phone' => 'Telp Pelanggan: ',
            'total_amount' => 'Total: ',
            'status' => 'Status: ',
            'status_canceled' => 'Dibatalkan',
        ],

        'payment-info-line-1' => 'Terima kasih.',
    ],
];
