<?php
/*
 | Table: orb_objects
 | Columns:
 | object_id   char(16) PK
 | merchant_id char(16)
 | object_name varchar(50)
 | object_type varchar(50)
 | object_order    int(10) UN
 | status  varchar(15)
 | created_at  timestamp
 | updated_at  timestamp
*/
$factory('Object', [
    'merchant_id' => 'factory:Mall',
    'object_name' => $faker->bothify('##?'),
    'object_type' => $faker->randomElement(['bank', 'floor']),
    'object_order'=> $faker->randomNumber(2),
    'status'      => 'active'
]);

$factory('Object', 'floor', [
    'merchant_id' => 'factory:Mall',
    'object_name' => $faker->bothify('##?'),
    'object_type' => 'floor',
    'object_order'=> $faker->randomNumber(2),
    'status'      => 'active'
]);