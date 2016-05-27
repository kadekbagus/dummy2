<?php
/*
 | Table: settings
 | Columns:
 | setting_id char(16) UN PK
 | setting_name varchar(100)
 | setting_value test
 | object_id char(16)
 | object_type varchar(100)
 | modified_by char(16)
 | status status(15)
 | created_at  timestamp
 | updated_at  timestamp
 */
$factory('Setting', [
    'setting_name' => 'enable_coupon_widget',
    'setting_value' => 'true',
    'object_id' => 'factory:Mall',
    'object_type' => 'merchant',
    'status' => 'active',
]);

