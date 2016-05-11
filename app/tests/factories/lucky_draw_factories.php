<?php

$factory('LuckyDraw', [
    'mall_id'           => 'factory:retailer_mall',
    'lucky_draw_name'   => $faker->word,
    'description'       => $faker->text,
    'start_date'        => date('Y-m-d H:i:s'),
    'end_date'          => date('Y-m-d H:i:s', strtotime('next month')),
    'minimum_amount'    => 100000,
    'min_number'        => 1001,
    'max_number'        => 5000,
    'status'            => 'active',
    'campaign_status_id' => 'factory:CampaignStatus',
]);