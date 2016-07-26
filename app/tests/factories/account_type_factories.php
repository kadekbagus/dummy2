<?php

/*
 | Table: account_types
 | Columns:
 | account_type_id char(16) PK
 | type_name   varchar(20)
 | unique_rule varchar(15)
 | account_order   tinyint(3) UN
 | status  varchar(15)
 | created_by  char(16)
 | modified_by char(16)
 | created_at  timestamp
 | updated_at  timestamp
*/

$factory('AccountType', [
    'type_name'     => 'Agency',
    'unique_rule'   => 'mall_tenant',
    'account_order' => 0,
    'status'        => 'active',
]);

$factory('AccountType', 'account_type_mall', [
    'type_name'     => 'Mall',
    'unique_rule'   => 'mall',
    'account_order' => 0,
    'status'        => 'active',
]);

$factory('AccountType', 'account_type_merchant', [
    'type_name'     => 'Merchant',
    'unique_rule'   => 'tenant',
    'account_order' => 1,
    'status'        => 'active',
]);

$factory('AccountType', 'account_type_agency', [
    'type_name'     => 'Agency',
    'unique_rule'   => 'mall_tenant',
    'account_order' => 2,
    'status'        => 'active',
]);

$factory('AccountType', 'account_type_3rd', [
    'type_name'     => '3rd Party',
    'unique_rule'   => 'none',
    'account_order' => 3,
    'status'        => 'active',
]);

$factory('AccountType', 'account_type_dominopos', [
    'type_name'     => 'Dominopos',
    'unique_rule'   => 'none',
    'account_order' => 4,
    'status'        => 'active',
]);