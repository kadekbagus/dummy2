<?php

return [
    'subject' => 'Waiting for your Payment',

    'header' => [
        'invoice'       => 'Notice',
    ],

    'body' => [
        'greeting' => 'Yth, :customerName
                        <br>
                        Terima kasih telah membeli Kupon di Gotomalls.com. Berikut ini instruksi untuk menyelesaikan pembayaran Anda.',

        'greeting_pulsa' => 'Yth, :customerName
                        <br>
                        Terima kasih telah membeli Pulsa di Gotomalls.com. Silakan lakukan pembayaran menggunakan aplikasi GOJEK di HP Anda. Berikut ini detail transaksi Anda.',

        'transaction_labels' => [
            'transaction_id' => 'No. Transaksi: ',
            'transaction_date' => 'Tanggal Transaksi: ',
            'coupon_name' => 'Nama Kupon: ',
            'coupon_price' => 'Harga Kupon: ',
            'pulsa_name' => 'Pulsa: ',
            'pulsa_phone_number' => 'No. Telp: ',
            'pulsa_price' => 'Harga: ',
            'coupon_quantity' => 'Jumlah: ',
            'customer_name' => 'Nama Pelanggan: ',
            'email' => 'Email: ',
            'phone' => 'No. Telp Pelanggan: ',
            'total_amount' => 'Total: ',
        ],

        'payment-info-line-1' => 'Untuk menyelesaikan transaksi ini, mohon untuk melakukan transfer ke rekening berikut sebelum <br><span style="color:#f43d3c;"><strong>:paymentExpiration</strong></span>',

        'payment-info' => [
            'biller_code' => 'Kode Perusahaan: ',
            'bill_key' => 'Kode Pembayaran: ',

            'bank_code' => 'Kode Bank: ',
            'bank_account_number' => 'Nomor Rekening: ',

            'bank_name' => 'Nama Bank: ',
        ],

        'payment-info-line-2' => 'Anda dapat mengikuti petunjuk pembayaran di bawah ini untuk menyelesaikan transaksi.',
        'payment-info-line-3' => 'Abaikan email ini apabila Anda telah membatalkan pembelian.',

        'btn_payment_instruction' => 'Instruksi Pembayaran',
        'btn_my_wallet' => 'Buka Dompet Saya',
        'btn_cancel_purchase' => 'Batalkan Pembelian',
    ],
];
