<?php

$factory('LuckyDrawReceipt', [
    'mall_id'           => 'factory:retailer_mall',
    'user_id'           => 'factory:User',
    'receipt_number'    => $faker->randomNumber(),
    'receipt_date'      => date('Y-m-d H:i:s'),
    'receipt_payment_type'  => 'cash',
    'receipt_amount'    => $faker->randomFloat(2, 0, 500000),
    'receipt_group'     => $faker->uuid,
    'status'            => 'active'
]);