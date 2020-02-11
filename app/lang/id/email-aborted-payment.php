<?php

return [
    'subject' => 'Kuponmu Ketinggalan, Nih! - Gotomalls.com',
    'subject_pulsa' => 'Pulsamu Ketinggalan, Nih! – Gotomalls.com',
    'subject_data_plan' => 'Paket Datamu Ketinggalan, Nih! – Gotomalls.com',
    'subject_digital_product' => ':productType Kamu Ketinggalan, Nih! - Gotomalls.com',

    'header' => [
        'email-type'       => 'Pesanan Dibatalkan',
    ],

    'body' => [
        'greeting' => '<strong>Hai, :customerName</strong>
                        <br>
                        Melalui email ini kami memberitahukan bahwa transaksi Anda baru saja dibatalkan. Berikut ini detail transaksi tersebut.',

        'greeting_digital_product' => [
            'customer_name' => '<strong>Hai, :customerName</strong>',
            'body' => 'Melalui email ini kami memberitahukan bahwa transaksi Anda baru saja dibatalkan. Berikut ini detail transaksi tersebut.',
        ],

        'transaction_labels' => [
            'transaction_id' => 'No. Transaksi: ',
            'transaction_date' => 'Tanggal Transaksi: ',
            'coupon_name' => 'Nama Kupon: ',
            'coupon_price' => 'Harga Kupon: ',
            'pulsa_name' => 'Pulsa/Paket Data: ',
            'pulsa_phone_number' => 'No. HP: ',
            'pulsa_price' => 'Harga Pulsa/Paket Data: ',
            'coupon_quantity' => 'Jumlah: ',
            'customer_name' => 'Nama Pelanggan: ',
            'email' => 'Email: ',
            'phone' => 'Telp Pelanggan: ',
            'total_amount' => 'Total: ',
            'status' => 'Status: ',
            'status_aborted' => 'Dibatalkan',
            'product_name' => 'Produk: ',
        ],

        'payment-info-line-1' => 'Gotomalls.com mendeteksi bahwa kamu <strong>membatalkan</strong> transaksi pada tanggal :transactionDateTime.',
        'payment-info-line-2' => 'Apakah terdapat kendala dalam melakukan transaksi pembelianmu?
Bantu Gotomalls.com dengan memberikan feedback dan membalas email ini dengan keluhan atau alasanmu tidak menyelesaikan pembelian ini, ya.
Apabila kamu mengalami kesulitan, silahkan <strong>tanyakan keluhanmu</strong> melalui email <a style="text-decoration: none;color:#f43d3c;" href="mailto::email">:email</a> dan Gotomalls.com akan siap membantu.',

        'payment-info-line-1-pulsa' => 'Gotomalls.com mendeteksi bahwa kamu <strong>membatalkan</strong> transaksi pulsa pada tanggal :transactionDateTime.',
        'payment-info-line-2-pulsa' => 'Apakah terdapat kendala dalam melakukan transaksi pembelian pulsamu?
Bantu Gotomalls.com dengan memberikan feedback dan membalas email ini dengan keluhan atau alasanmu tidak menyelesaikan pembelian pulsa ini, ya.
Apabila kamu mengalami kesulitan dalam pembelian pulsa, silahkan <strong>tanyakan keluhanmu</strong> melalui email <a style="text-decoration: none;color:#f43d3c;" href="mailto::email">:email</a> dan Gotomalls.com akan siap membantu.',


        'payment-info-line-1-data-plan' => 'Gotomalls.com mendeteksi bahwa kamu <strong>membatalkan</strong> transaksi paket data pada tanggal :transactionDateTime.',
        'payment-info-line-2-data-plan' => 'Apakah terdapat kendala dalam melakukan transaksi pembelian paket datamu?
Bantu Gotomalls.com dengan memberikan feedback dan membalas email ini dengan keluhan atau alasanmu tidak menyelesaikan pembelian paket data ini, ya.
Apabila kamu mengalami kesulitan dalam pembelian paket data, silahkan <strong>tanyakan keluhanmu</strong> melalui email <a style="text-decoration: none;color:#f43d3c;" href="mailto::email">:email</a> dan Gotomalls.com akan siap membantu.',

        'regards' => 'Terima kasih.<br><br>Salam,<br>Gotomalls.com Customer Service Team',

        'buttons' => [
            'buy_coupon' => 'Beli Kupon Sekarang',
            'buy_pulsa' => 'Beli Pulsa Sekarang',
            'buy_data_plan' => 'Beli Paket Data Sekarang',
            'buy_digital_product' => 'Beli :productType Lain',
        ]
    ],
];
