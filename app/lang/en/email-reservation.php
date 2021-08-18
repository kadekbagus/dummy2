<?php

return [
    'labels' => [
        'reservation_details' => 'Reservation Details',
        'transaction_id' => 'Reservation ID',
        'user_email' => 'User Email',
        'store_location' => 'Store Location',
        'store_location_detail' => ':storeName at :mallName',
        'reserve_date' => 'Reservation Date',
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
        'btn_see_reservation' => 'See Reservation',
        'status_detail' => [
            'pending' => 'Pending',
            'cancelled' => 'Cancelled',
            'accepted' => 'Reserved',
            'declined' => 'Declined',
            'expired' => 'Expired',
        ],
        'reason' => 'Decline Reason',
    ],

    'made' => [
        'subject' => 'New Product Reservation!',
        'title' => 'New Reservation',
        'greeting' => 'Hello :recipientName,',
        'body' => [
            'line-1' => 'New reservation has been created.
                Please make sure the product is available and in accordance with the reservation.',
            'line-2' => 'Please confirm the reservation immediately.',
        ],
    ],

    'canceled' => [
        'subject' => 'Product Reservation Canceled',
        'title' => 'Reservation Canceled',
        'greeting' => 'Hello :recipientName,',
        'body' => [
            'line-1' => 'Customer just canceled following reservation.',
        ],
    ],

    'accepted' => [
        'subject' => 'Reservation Accepted',
        'title' => 'Reservation Accepted',
        'greeting' => 'Hello :recipientName,',
        'body' => [
            'line-1' => 'Your reservation has been accepted by :storeName at :mallName.
                You can make payments by showing the transaction id to the staff at the specified store location.',
            'line-2' => 'Please pay attention to the expiration date to avoid cancelling the reservation.',
        ],
    ],

    'declined' => [
        'subject' => 'Reservation Declined',
        'title' => 'Reservation Declined',
        'greeting' => 'Hello :recipientName,',
        'body' => [
            'line-1' => 'We are very sorry to inform you that your reservation was declined by :storeName at :mallName.',
        ],
    ],

    'expired' => [
        'subject' => 'Reservation Expired',
        'title' => 'Reservation Expired',
        'greeting' => 'Hello :recipientName,',
        'body' => [
            'line-1' => 'Unfortunately, your product reservation was expired on :expirationTime. Below are the details.',
        ],
        'body-admin' => [
            'line-1' => 'We have an expired reservation below.',
        ],
    ],
];
