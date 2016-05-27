<?php

$factory('CampaignAge', [
    'campaign_type' => $faker->randomElement(['coupon', 'promotion', 'news']),
    'campaign_id'   => $faker->randomDigitNotNull,
    'age_range_id'  => 'factory:AgeRange'
]);
