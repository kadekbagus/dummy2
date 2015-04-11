<?php
/*
 | Table: product_variants
 | Columns:
 | product_variant_id  bigint(20) UN AI PK
 | product_id  bigint(20) UN
 | price   decimal(14,2)
 | upc varchar(30)
 | sku varchar(30)
 | stock   int(10) UN
 | product_attribute_value_id1 int(10) UN
 | product_attribute_value_id2 int(10) UN
 | product_attribute_value_id3 int(10) UN
 | product_attribute_value_id4 int(10) UN
 | product_attribute_value_id5 int(10) UN
 | merchant_id bigint(20) UN
 | status  varchar(15)
 | retailer_id bigint(20) UN
 | default_variant char(3)
 | created_by  bigint(20) UN
 | modified_by bigint(20) UN
 | created_at  timestamp
 | updated_at  timestamp
 */
$factory('ProductVariant', [
    'status' => 'active',
]);
