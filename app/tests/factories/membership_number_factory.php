<?php
/**
 * Used to generate membership number for a membership card.
 */
$factory('MembershipNumber', [
    'membership_id'         => 'factory:Membership',
    'user_id'               => 'factory:User',
    'membership_number'     => $faker->randomNumber(5),
    'status'                => 'active',
    'expired_date'          => date('Y-m-d H:i:s', strtotime('next year')),
    'join_date'             => date('Y-m-d H:i:s'),
    'issuer_merchant_id'    => 'factory:Mall'
]);