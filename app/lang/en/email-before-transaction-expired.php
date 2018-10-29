<?php

return [
    'subject' => 'Finish Your Payment',

    'header' => [
        'invoice'       => 'Notice',
    ],

    'body' => [
        'greeting' => 'Dear, :customerName
                        <br>
                        Thank you for purchasing Coupon in Gotomalls.com. It looks like you still have unpaid transaction. Below are the instructions to complete your payment.',

        'transaction_labels' => [
            'transaction_id' => 'Transaction ID: ',
            'transaction_date' => 'Transaction Date: ',
            'coupon_name' => 'Coupon Name: ',
            'coupon_price' => 'Coupon Price: ',
            'coupon_quantity' => 'Quantity: ',
            'customer_name' => 'Customer Name: ',
            'email' => 'Email: ',
            'phone' => 'Phone: ',
            'total_amount' => 'Total Amount: ',
        ],

        'payment-info-line-1' => 'Please perform the payment transfer to the following bank account before <br><span style="color:#f43d3c;"><strong>:paymentExpiration</strong></span> to complete your transaction.',

        'payment-info' => [
            'biller_code' => 'Company Code: ',
            'bill_key' => 'Payment Code: ',

            'bank_code' => 'Bank Code: ',
            'bank_account_number' => 'Bank Account Number: ',

            'bank_name' => 'Bank Name: ',
        ],

        'payment-info-line-2' => 'You can follow payment instructions below to complete the transaction.',
        'payment-info-line-3' => 'If you canceled the purchase, then ignore this email.',

        'btn_payment_instruction' => 'Payment Instructions',
        'btn_my_wallet' => 'Go to My Wallet',
        'btn_cancel_purchase' => 'Cancel Transaction',
    ],
];
