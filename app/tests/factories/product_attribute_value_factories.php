<?php
/*
 | Table: product_attribute_values
 | Columns:
 | product_attribute_value_id  int(10) UN AI PK
 | product_attribute_id    int(11) UN
 | value   varchar(100)
 | value_order tinyint(3) UN
 | status  varchar(15)
 | created_at  timestamp
 | updated_at  timestamp
 | created_by  bigint(20) UN
 | modified_by bigint(20) UN
*/

$factory('ProductAttributeValue', [
    'value' => $faker->word(2),
    'status' => 'active',
    'value_order' => 0,
    'created_by'  => 'factory:User',
    'product_attribute_id' => 'factory:ProductAttribute'
]);
