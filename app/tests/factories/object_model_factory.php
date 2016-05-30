<?php

$factory('Object', [
    'merchant_id' => 'factory:Mall',
    'object_name' => $faker->bothify('##?'),
    'object_type' => $faker->randomElement(['bank', 'floor']),
    'object_order'=> $faker->randomNumber(2),
    'status'      => 'active'
]);
