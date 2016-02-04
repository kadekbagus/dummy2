<?php
/**
 * Used to generate Membership Card.
 */
$factory('Membership', [
    'merchant_id'       => 'factory:Mall',
    'membership_name'   => $faker->Company,
    'description'       => $faker->text,
    'status'            => 'active'
]);