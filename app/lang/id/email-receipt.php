<?php 

return [
    'header' => [
        'invoice'       => 'Invoice',
        'order_number'  => 'No. Order: :transactionId',
    ],

    'body' => [
        'greeting' => 'Dear, :customerName
                        <br>
                        Thank you for purchasing :itemName in Gotomalls.com. Your payment has been verified by our system. Below is the transaction summary of your purchase',

        'redeem' => 'Untuk melihat barang yang telah dibeli dan melakukan redeem di toko, silakan klik tombol di bawah',

        'help' => 'Please contact our customer service at :csPhone or email at <a href="mailto::csEmail">:csEmail</a> if you find any difficulties.',
        'thank_you' => 'Thank you and have a nice day.',
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
