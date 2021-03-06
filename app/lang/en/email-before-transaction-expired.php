<?php

return [
    'subject' => 'Payment Reminder',

    'header' => [
        'invoice' => 'Notice',
    ],

    'body' => [
        'greeting' => 'Dear, :customerName
                        <br>
                        Your coupon(s) is still waiting for you. The payment limit will expire <span style="color:#f43d3c">today</span>!',

        'greeting_pulsa' => 'Dear, :customerName
                        <br>
                        Your Pulsa/Data Plan is still waiting for you. The payment limit will expire <span style="color:#f43d3c">today</span>!',

        'greeting_digital_product' => [
            'customer_name' => 'Dear, :customerName',
            'body' => 'Your :productType is still waiting for you. The payment limit will expire <span style="color:#f43d3c">today</span>! Please perform the payment using your :paymentMethod App. Below are your transaction details.',
        ],

        'transaction_labels' => [
            'transaction_id' => 'Transaction ID: ',
            'transaction_date' => 'Transaction Date: ',
            'coupon_name' => 'Coupon Name: ',
            'coupon_price' => 'Coupon Price: ',
            'pulsa_phone_number' => 'Phone Number: ',
            'pulsa_name' => 'Pulsa/Data Plan: ',
            'pulsa_price' => 'Price: ',
            'coupon_quantity' => 'Quantity: ',
            'customer_name' => 'Customer Name: ',
            'email' => 'Email: ',
            'phone' => 'Phone: ',
            'total_amount' => 'Total Amount: ',
            'product' => 'Product: ',
        ],

        'payment-info-line-1' => 'Please perform the payment transfer to the following bank account before <br><span style="color:#f43d3c;"><strong>:paymentExpiration</strong></span> to complete your transaction.',

        'payment-info-line-1-gopay' => 'Please perform the payment using your GOJEK App <br><span style="color:#f43d3c;"><strong>:paymentExpiration</strong></span> to complete your transaction.',

        'payment-info' => [
            'biller_code' => 'Company Code: ',
            'bill_key' => 'Payment Code: ',

            'bank_code' => 'Bank Code: ',
            'bank_account_number' => 'Bank Account Number: ',

            'bank_name' => 'Bank Name: ',
        ],

        'payment-info-line-2' => 'You can follow payment instructions below to complete the transaction.',
        'payment-info-line-3' => 'If you completed/canceled the purchase, then ignore this email.',

        'btn_payment_instruction' => 'Payment Instructions',
        'btn_my_wallet' => 'Go to My Wallet',
        'btn_cancel_purchase' => 'Cancel Transaction',
    ],
];
