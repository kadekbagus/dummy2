<?php

$factory('UserSignin', [
    'user_id'       => 'factory:User',
    'signin_via'    => $faker->randomElement(['form', 'google', 'facebook', 'guest']),
    'location_id'   => 'factory:Mall'
]);
