<?php

return [
    'labels' => [
        'reservation_details' => 'Reservation Details',
        'transaction_id' => 'Transaction ID',
        'user_email' => 'User Email',
        'store_location' => 'Store Location',
        'store_location_detail' => ':storeName at :mallName',
        'reserve_date' => 'Reservation Date',
        'expiration_date' => 'Expiration Data',
        'quantity' => 'Quantity',
        'total_payment' => 'Total Payment',
        'status' => 'Status',
        'product_details' => 'Product Details',
        'product_name' => 'Product Name',
        'product_variant' => 'Product Variant',
        'product_sku' => 'Product SKU',
        'product_barcode' => 'Product Barcode',
        'btn_accept' => 'Accept',
        'btn_decline' => 'Decline',
        'status' => [
            'pending' => 'Pending',
            'cancelled' => 'Cancelled',
            'accepted' => 'Accepted',
            'declined' => 'Declined',
        ],
        'reason' => 'Decline Reason',
    ],

    'made' => [
        'subject' => 'New Product Reservation!',
        'title' => 'New Reservation',
        'greeting' => 'Hello Admin,',
        'body' => [
            'line-1' => 'New reservation has been created.
                Please make sure the product is available and in accordance with the reservation.',
            'line-2' => 'Please confirm the reservation immediately.',
        ],
    ],

    'canceled' => [
        'subject' => 'Product Reservation Canceled',
        'title' => 'Reservation Canceled',
        'greeting' => 'Hello Admin,',
        'body' => [
            'line-1' => 'Customer just canceled following reservation.',
        ],
    ],

    'accepted' => [
        'subject' => 'Reservation Accepted',
        'title' => 'Reservation Accepted',
        'greeting' => 'Hello :customerName,',
        'body' => [
            'line-1' => 'Your reservation has been accepted by :storeName at :mallName.
                You can make payments by showing the transaction id to the staff at the specified store location.',
            'line-2' => 'Please pay attention to the expiration date to avoid cancelling the reservation.',
        ],
    ],

    'declined' => [
        'subject' => 'Reservation Declined',
        'title' => 'Reservation Declined',
        'greeting' => 'Hello :customerName,',
        'body' => [
            'line-1' => 'We are very sorry to inform you that your reservation was declined by :storeName at :mallName.',
        ],
    ],
];
