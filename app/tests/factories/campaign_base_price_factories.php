<?php
/*
 | Table: campaign_base_prices
 | Columns:
 | campaign_base_price_id  char(16) PK
 | merchant_id char(16)
 | price   decimal(10,2)
 | campaign_type   varchar(50)
 | status  varchar(15)
 | created_by  char(16)
 | modified_by char(16)
 | created_at  timestamp
 | updated_at  timestamp
*/

$factory('CampaignBasePrice', [
    'merchant_id'   => 'factory:Mall',
    'price'         => $faker->numberBetween(100, 500),
    'campaign_type' => $faker->randomElement(['coupon', 'promotion', 'news']),
    'status'        => 'active'
]);
