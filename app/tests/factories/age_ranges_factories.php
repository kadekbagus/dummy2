<?php

$factory('AgeRange', [
    'merchant_id'   => 'factory:Mall',
    'range_name'    => $faker->lexify('????????'),
    'min_value'     => $faker->numberBetween(0, 25),
    'min_value'     => $faker->numberBetween(26, 50),
    'status'        => 'active'
]);
