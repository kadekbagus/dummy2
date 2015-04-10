<?php
/*
 | Columns:
 | merchant_tax_id int(10) UN AI PK
 | merchant_id int(10) UN
 | tax_name    varchar(50)
 | tax_type    varchar(15)
 | tax_value   decimal(5,4)
 | tax_order   tinyint(3) UN
 | status  varchar(15)
 | created_by  bigint(20) UN
 | modified_by bigint(20) UN
 | created_at  timestamp
 | updated_at  timestamp
*/

$factory('MerchantTax', [
    'merchant_id' => 'factory:Merchant',
    'tax_type'    => 'government',
    'tax_name'    => 'PPN',
    'tax_value'  => 10,
    'tax_order'   => 0,
]);
