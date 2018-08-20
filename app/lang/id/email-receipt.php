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

        'redeem' => 'Untuk melihat barang yang telah dibeli dan melakukan redeem di toko, silakan klik tombol di bawah',

        'help' => 'Jika menemui kendala terkait pembelian, silakan hubungi layanan bantuan kami di nomor <a style="color:#f43d3c;text-decoration:none;" href="tel::csPhone">:csPhone</a> atau surel di <a style="color:#f43d3c;text-decoration:none;" href="mailto::csEmail">:csEmail</a>.',
        'thank_you' => 'Terima kasih dan kami tunggu pembelian berikutnya!',
    ],

    'table_customer_info' => [
        'header' => [
            'customer' => 'Customer',
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
    ],
];
