<?php
/**
 * Table: user_merchant
 * Columns:
 * user_merchant_id char(16) UN PK
 * user_id char(16)
 * object_id char(16)
 * created_at  timestamp
 * updated_at  timestamp
 */
$factory('UserMerchant', [
    'user_id'       => 'factory:User',
    'merchant_id' => 'factory:Merchant',
    'object_type'  => $faker->randomElement(['mall', 'tenant']),
]);