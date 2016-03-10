<?php
/*
 | Table: promotions
 | Columns:
 | promotion_id    int(10) UN AI PK
 | merchant_id int(10) UN
 | promotion_name  varchar(255)
 | promotion_type  varchar(15)
 | description varchar(2000)
 | begin_date  datetime
 | end_date    datetime
 | is_permanent    char(1)
 | status  varchar(15)
 | image   varchar(255)
 | is_coupon   char(1)
 | maximum_issued_coupon   int(11)
 | coupon_validity_in_days int(11)
 | coupon_notification char(1)
 | created_by  bigint(20) UN
 | modified_by bigint(20) UN
 | created_at  timestamp
 | updated_at  timestamp
 */

$factory('Coupon', [
    'promotion_name' => $faker->words(3),
    'promotion_type' => 'product',
    'merchant_id'    => 'factory:Merchant',
    'status'         => 'active',
    'begin_date'     => $faker->dateTimeBetween('-2 months', '-2 weeks'),
    'end_date'       => $faker->dateTimeBetween('-2 days'),
    'coupon_notification' => 'Y',
    'campaign_status_id' => 'factory:CampaignStatus',
]);

