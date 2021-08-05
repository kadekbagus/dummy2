<?php

return [
    'new-order' => [
        'subject' => 'New Product Order',
        'title' => 'New Order',
        'body' => [
            'greeting' => 'Dear :recipientName,',
            'body_1' => 'You have new product order! Please find the details below and take necessary actions.',

            'view_my_purchases' => '',

            'help' => 'Please contact our customer service at <a style="color:#f43d3c;text-decoration:none;" href="tel::csPhone">:csPhone</a> or email at <a style="color:#f43d3c;text-decoration:none;" href="mailto::csEmail">:csEmail</a> if you find any difficulties.',
            'thank_you' => '',
        ],
    ],
    'buttons' => [
        'follow_up_order' => 'Process Order',
    ],
];
