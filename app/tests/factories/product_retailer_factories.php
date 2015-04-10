<?php
/*
 | Table: product_retailer
 | Columns:
 | product_retailer_id bigint(20) UN AI PK
 | product_id  bigint(20) UN
 | retailer_id int(10) UN
 | created_at  timestamp
 | updated_at  timestamp
*/

$factory('ProductRetailer', [
    'product_id' => 'factory:Product',
    'retailer_id' => 'factory:Merchant'
]);
