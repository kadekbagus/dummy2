<?php

$factory('IssuedCoupon', [
    'promotion_id' => 'factory:Coupon',
    'issued_coupon_code' => $faker->randomDigitNotNull,
    'user_id' => 'factory:User',
    'expired_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
    'issued_date' => date('Y-m-d H:i:s', strtotime('now')),
    'status' => 'active'
]);
