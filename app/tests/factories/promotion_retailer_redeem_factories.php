<?php
/*
 | Table: promotion_retailer_redeem
 | Columns:
 | promotion_retailer_redeem_id int(10) UN AI PK
 | promotion_id int(10) UN
 | retailer_id  int(10) UN
 | created_at   timestamp
 | updated_at   timestamp
*/

$factory('CouponRetailerRedeem', [
    'promotion_id' => 'factory:Promotion',
    'retailer_id'  => 'factory:Merchant'
]);
