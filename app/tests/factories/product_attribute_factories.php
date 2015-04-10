<?php
/*
 | Table: product_attributes
 | Columns:
 | product_attribute_id    int(10) UN AI PK
 | product_attribute_name  varchar(50)
 | merchant_id int(10) UN
 | status  varchar(15)
 | created_at  timestamp
 | updated_at  timestamp
 | created_by  bigint(20) UN
 | modified_by bigint(20) UN
 */

$factory('ProductAttribute', [
    'product_attribute_name' => $faker->word(),
    'status' => 'active',
    'merchant_id' => 'factory:Merchant',
    'created_by'  => 'factory:User'
]);
