<?php
/*
 | Table: category_merchant
 | Columns:
 | category_merchant_id    char(16) PK
 | category_id char(16)
 | merchant_id char(16)
 | created_at  timestamp
 | updated_at  timestamp
 */

$factory('CategoryMerchant', [
    'category_id' => 'factory:Category',
    'merchant_id' => 'factory:Tenant'
]);
