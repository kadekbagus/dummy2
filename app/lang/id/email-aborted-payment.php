<?php

return [
    'subject' => 'Kuponmu Ketinggalan, Nih! - Gotomalls.com',
    'subject_pulsa' => 'Pulsamu Ketinggalan, Nih! – GoToMalls.com',

    'header' => [
        'email-type'       => 'Pesanan Dibatalkan',
    ],

    'body' => [
        'greeting' => '<strong>Hai, :customerName !</strong>
                        <br>
                        Melalui email ini kami memberitahukan bahwa transaksi Anda baru saja dibatalkan. Berikut ini detail transaksi tersebut.',

        'transaction_labels' => [
            'transaction_id' => 'No. Transaksi: ',
            'transaction_date' => 'Tanggal Transaksi: ',
            'coupon_name' => 'Nama Kupon: ',
            'coupon_price' => 'Harga Kupon: ',
            'pulsa_name' => 'Pulsa: ',
            'pulsa_phone_number' => 'No. HP Pulsa: ',
            'pulsa_price' => 'Harga Pulsa: ',
            'coupon_quantity' => 'Jumlah: ',
            'customer_name' => 'Nama Pelanggan: ',
            'email' => 'Email: ',
            'phone' => 'Telp Pelanggan: ',
            'total_amount' => 'Total: ',
            'status' => 'Status: ',
            'status_aborted' => 'Dibatalkan',
        ],

        'payment-info-line-1' => 'GoToMalls.com mendeteksi bahwa kamu <strong>membatalkan</strong> transaksi pada tanggal :transactionDateTime.',
        'payment-info-line-2' => 'Apakah terdapat kendala dalam melakukan transaksi pembelianmu?
Bantu GoToMalls.com dengan memberikan feedback dan membalas email ini dengan keluhan atau alasanmu tidak menyelesaikan pembelian ini, ya.
Apabila kamu mengalami kesulitan, silahkan <strong>tanyakan keluhanmu</strong> melalui email <a style="text-decoration: none;color:#f43d3c;" href="mailto::email">:email</a> dan GoToMalls.com akan siap membantu.',

        'payment-info-line-1-pulsa' => 'GoToMalls.com mendeteksi bahwa kamu <strong>membatalkan</strong> transaksi pulsa pada tanggal :transactionDateTime.',
        'payment-info-line-2-pulsa' => 'Apakah terdapat kendala dalam melakukan transaksi pembelian pulsamu?
Bantu GoToMalls.com dengan memberikan feedback dan membalas email ini dengan keluhan atau alasanmu tidak menyelesaikan pembelian pulsa ini, ya.
Apabila kamu mengalami kesulitan dalam pembelian pulsa, silahkan <strong>tanyakan keluhanmu</strong> melalui email <a style="text-decoration: none;color:#f43d3c;" href="mailto::email">:email</a> dan GoToMalls.com akan siap membantu.',

        'regards' => 'Terima kasih.<br><br>Salam,<br>Gotomalls.com Customer Service Team',

        'buttons' => [
            'buy_coupon' => 'Beli Kupon Sekarang',
            'buy_pulsa' => 'Beli Pulsa Sekarang',
        ]
    ],
];
