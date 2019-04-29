<?php

return [
    'subject' => 'Selesaikan Pembayaran Anda',

    'header' => [
        'invoice' => 'Pemberitahuan',
    ],

    'body' => [
        'greeting' => 'Yth, :customerName
                        <br>
                        Kupon Anda sedang menunggu. Batas waktu pembayaran Anda adalah hari ini!',

        'greeting_pulsa' => 'Yth, :customerName
                        <br>
                        Pulsa Anda sedang menunggu. Batas waktu pembayaran Anda adalah hari ini.',

        'transaction_labels' => [
            'transaction_id' => 'ID Transaksi: ',
            'transaction_date' => 'Tanggal Transaksi: ',
            'coupon_name' => 'Nama Kupon/Voucher: ',
            'coupon_price' => 'Harga Kupon/Voucher: ',
            'pulsa_phone_number' => 'No. Telp Pulsa',
            'pulsa_name' => 'Pulsa',
            'pulsa_price' => 'Harga Pulsa',
            'coupon_quantity' => 'Jumlah: ',
            'customer_name' => 'Nama Pelanggan: ',
            'email' => 'Email: ',
            'phone' => 'Telp/HP: ',
            'total_amount' => 'Jumlah Total: ',
        ],

        'payment-info-line-1' => 'Untuk menyelesaikan transaksi, silakan lakukan pembayaran dengan transfer ke bank berikut sebelum <br><span style="color:#f43d3c;"><strong>:paymentExpiration</strong></span>.',

        'payment-info-line-1-gopay' => 'Please perform the payment using your GOJEK App <br><span style="color:#f43d3c;"><strong>:paymentExpiration</strong></span> to complete your transaction.',

        'payment-info' => [
            'biller_code' => 'Kode Perusahaan: ',
            'bill_key' => 'Kode Pembayaran: ',

            'bank_code' => 'Kode Bank: ',
            'bank_account_number' => 'Nomor VA: ',

            'bank_name' => 'Nama Bank: ',
        ],

        'payment-info-line-2' => 'Anda dapat mengikuti petunjuk pembayaran di bawah ini untuk menyelesaikan transaksi.',
        'payment-info-line-3' => 'Abaikan email ini apabila transaksi telah dibatalkan.',

        'btn_payment_instruction' => 'Petunjuk Pembayaran',
        'btn_my_wallet' => 'Buka Pembelian Saya',
        'btn_cancel_purchase' => 'Batalkan Transaksi',
    ],
];
