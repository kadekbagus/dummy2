<?php

return [
    'header' => 'Transfer Coupon',

    'confirm' => [
        'subject' => ':ownerName gives you a Coupon',
        'greeting' => 'Hi, :recipientName',
        'message' => ':ownerName wants to give you a coupon so you can use or redeem it.
            Do you want to accept the coupon?',

        'btn_accept' => 'Accept',
        'btn_decline' => 'Decline',
    ],

    'canceled' => [
        'subject' => 'Coupon Transfer Canceled',
        'greeting' => 'Hi, :recipientName',
        'message' => ':ownerName has canceled the transfer coupon.
            So we will not send the coupon to yo. Just ignore the previous email and do not take any action on it.
            Thank you.',
    ],

    'declined' => [
        'subject' => 'Coupon Transfer Rejected',
        'greeting' => 'Hi, :ownerName',
        'message' => ':recipientName has decided to reject the coupon you gave.
            Your coupon has been added back to your wallet. Use the button below to see coupon.',

        'btn_open_my_wallet' => 'Open My Wallet',
    ],
];
