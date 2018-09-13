<?php

return [
    'subject' => 'Waiting for your Payment',

    'header' => [
        'invoice'       => 'Waiting Payment',
        'order_number'  => 'Transaction ID: :transactionId',
    ],

    'body' => [
        'greeting' => 'Dear, :customerName
                        <br>
                        You are one step closer to get :itemName ! Below is the transaction summary of your purchase',

        'payment-info-line-1' => 'To get your coupon, please complete the payment within 24 hours to the following bank/account number:',

        'payment-info' => [
            'biller_code' => 'Company Code',
            'bill_key' => 'Payment Code',

            'bank_code' => 'Bank Code',
            'bank_account_number' => 'Bank Account Number',

            'payment-method' => 'Payment Method',
            'payment-method-echannel' => 'Multi Payment - :bank',
            'payment-method-bank-transfer' => 'Bank Transfer - :bank',
        ],

        'help' => 'Please contact our customer service at <a style="color:#f43d3c;text-decoration:none;" href="tel::csPhone">:csPhone</a> or email at <a style="color:#f43d3c;text-decoration:none;" href="mailto::csEmail">:csEmail</a> if you find any difficulties.',
        'thank_you' => 'Thank you and have a nice day.',

        'btn_payment_instruction' => 'View Payment Instruction',
    ],

    'table_customer_info' => [
        'header' => [
            'customer' => 'Customer',
            'phone' => 'Phone',
            'email' => 'Email',
        ],
    ],

    'table_transaction' => [
        'header' => [
            'item' => 'Item',
            'quantity' => 'Quantity',
            'price'     => 'Price',
            'subtotal'  => 'Subtotal',
        ],
        'footer' => [
            'total'     => 'Total',
        ]
    ],

    'table_payment_info' => [
        'label' => [
            'method' => 'Payment Method',
            'bank_name' => 'Bank Name',
            'bank_code' => 'Bank Code',
            'account_number' => 'Account Number'
        ],
    ]
];
