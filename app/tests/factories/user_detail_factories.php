<?php
/*
 | Table: user_details
 | Columns:
 | user_detail_id bigint(20) UN AI PK
 | user_id bigint(20)
 | ...
 | created_at  timestamp
 | updated_at  timestamp
 */
$factory('UserDetail', [
    'user_id'           => 'factory:User',
    'retailer_id'       => 'factory:Retailer',
    'address_line1'     => $faker->streetAddress,
    'phone'             => $faker->phoneNumber,
    'phone2'            => $faker->phoneNumber,
    'phone3'            => $faker->phoneNumber,
]);