<?php
/*
 | Table: orb_timezones
 | Columns:
 | timezone_id char(16) PK
 | timezone_name   varchar(100)
 | timezone_offset varchar(9)
 | timezone_order  tinyint(3) UN
 | created_at  timestamp
 | updated_at  timestamp
 */
$factory('Timezone', [
    'timezone_name'       => 'UTC'
]);

$factory('Timezone', 'timezone_jakarta', [
    'timezone_name'       => 'Asia/Jakarta'
]);

$factory('Timezone', 'timezone_makassar', [
    'timezone_name'       => 'Asia/Makassar'
]);