<?php

return [
    'labels' => [
        'order_details' => 'Order Details',
        'transaction_id' => 'Transaction ID',
        'order_id' => 'Order ID',
        'customer_name' => 'Customer Name',
        'customer_email' => 'Customer Email',
        'customer_phone' => 'Customer Phone',
        'store_location' => 'Store Location',
        'store_location_detail' => ':storeName at :mallName',
        'order_date' => 'Order Date',
        'expiration_date' => 'Expiration Date',
        'quantity' => 'Qty',
        'total_payment' => 'Total Payment',
        'status' => 'Status',
        'product_details' => 'Product Details',
        'product_name' => 'Product Name',
        'product_variant' => 'Variant',
        'product_sku' => 'SKU',
        'product_barcode' => 'Barcode',
        'product_price' => 'Price',
        'btn_follow_up' => 'Process Order',
        'status_detail' => [
            'pending' => 'Pending',
            'cancelled' => 'Cancelled',
            'accepted' => 'Reserved',
            'declined' => 'Declined',
            'expired' => 'Expired',
        ],
        'reason' => 'Decline Reason',
    ],

    'new-order' => [
        'subject' => 'New Product Order!',
        'title' => 'New Order',
        'greeting' => 'Hello :recipientName,',
        'body' => [
            'line-1' => 'You have new product order! Please find the details below and take necessary action.',

            'help' => 'Please contact our customer service at <a style="color:#f43d3c;text-decoration:none;" href="tel::csPhone">:csPhone</a> or email at <a style="color:#f43d3c;text-decoration:none;" href="mailto::csEmail">:csEmail</a> if you find any difficulties.',
            'thank_you' => '',
        ],
    ],

    'pickup-order' => [
        'subject' => 'Product Ready To Pickup!',
        'title' => 'Ready To Pickup',
        'greeting' => 'Hello :recipientName,',
        'body' => [
            'line-user' => 'Your orders are ready for pickup! Go to your selected pickup store location and show this pickup code to store cashier.',
            'line-admin' => 'You have ready for pickup order! Please find the details below and take necessary action.',
            'help' => 'Please contact our customer service at <a style="color:#f43d3c;text-decoration:none;" href="tel::csPhone">:csPhone</a> or email at <a style="color:#f43d3c;text-decoration:none;" href="mailto::csEmail">:csEmail</a> if you find any difficulties.',
            'thank_you' => '',
        ],
        'btn_follow_up' => [
            'user' => 'Go to My Purchase',
            'admin' => 'Process Order'
        ]
    ],

    'complete-order' => [
        'subject' => 'Order Completed!',
        'title' => 'Order Completed',
        'greeting' => 'Hello :recipientName,',
        'body' => [
            'line-user' => 'Your orders are now completed! Thank you for your purchase.',
            'line-admin' => 'This order are now completed! Please find the details below and take necessary action.',
            'help' => 'Please contact our customer service at <a style="color:#f43d3c;text-decoration:none;" href="tel::csPhone">:csPhone</a> or email at <a style="color:#f43d3c;text-decoration:none;" href="mailto::csEmail">:csEmail</a> if you find any difficulties.',
            'thank_you' => '',
        ],
        'btn_follow_up' => [
            'user' => 'Go to My Purchase',
            'admin' => 'Process Order'
        ]
    ],
];
