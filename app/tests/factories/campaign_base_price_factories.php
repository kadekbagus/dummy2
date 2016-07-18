<?php

$factory('CampaignBasePrice', [
    'merchant_id'   => 'factory:Mall',
    'price'         => $faker->numberBetween(26, 1000),
    'campaign_type' => $faker->lexify('????????'),
    'status'        => 'active'
]);
