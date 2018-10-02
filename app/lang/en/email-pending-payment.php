<?php

return [
    'subject' => 'Waiting for your Payment',

    'header' => [
        'invoice'       => 'Notice',
    ],

    'body' => [
        'greeting' => 'Dear, :customerName
                        <br>
                        Thank you for purchasing Coupon in Gotomalls.com. Below is the instruction to complete your payment.',

        'transaction_labels' => [
            'transaction_id' => 'Transaction ID: ',
            'transaction_date' => 'Transaction Date: ',
            'coupon_name' => 'Coupon Name: ',
            'customer_name' => 'Customer Name: ',
            'email' => 'Email: ',
            'total_amount' => 'Total Amount: ',
        ],

        'payment-info-line-1' => 'Please perform the payment transfer to the following bank account before <span style="color:#f43d3c;">:paymentExpiration</span> to complete your transaction.',

        'payment-info' => [
            'biller_code' => 'Company Code: ',
            'bill_key' => 'Payment Code: ',

            'bank_code' => 'Bank Code: ',
            'bank_account_number' => 'Bank Account Number: ',

            'bank_name' => 'Bank Name: ',
        ],

        'payment-info-line-2' => 'You can follow payment instruction below to complete the transaction.',

        'btn_payment_instruction' => 'Payment Instruction',
    ],
];
