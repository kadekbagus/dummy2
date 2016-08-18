<?php

$factory('Activity', [
    'activity_name' => $faker->sentence(3),
    'activity_name_long' => $faker->sentence(3),
    'activity_type' => $faker->randomElement(['activation', 'click', 'coupon', 'create', 'delete', 'login', 'logout', 'mobileci', 'registration', 'reset_password', 'search', 'update', 'view']),
    'user_id' => 'factory:User',
    'user_email' => $faker->email,
    'full_name' => $faker->name,
    'group' => $faker->randomElement(['cs-portal', 'mobile-ci', 'portal']),
    'role_id' => 'factory:Role',
    'location_id' => 'factory:Mall',
    'ip_address' => $faker->ipv4,
    'from_wifi' => 'N',
    'user_agent' => $faker->userAgent,
    'status' => 'active',
]);