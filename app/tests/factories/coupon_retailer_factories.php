<?php
/*
 | Table: promotion_retailer
 | Columns:
 | promotion_retailer_id    int(10) UN AI PK
 | promotion_id int(10) UN
 | retailer_id  int(10) UN
 | created_at   timestamp
 | updated_at   timestamp
*/

$factory('CouponRetailer', [
    'promotion_id' => 'factory:Promotion',
    'retailer_id'  => 'factory:Merchant',
    'object_type'  => $faker->randomElement(['mall', 'tenant'])
]);

$factory('CouponRetailer', 'coupon_link_tenant', [
    'promotion_id' => 'factory:Coupon',
    'retailer_id'  => 'factory:Tenant',
    'object_type'  => $faker->randomElement(['mall', 'tenant'])
]);
