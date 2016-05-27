<?php
/*
 | Table: news
 | Columns:
 | news_id int(10) UN AI PK
 | mall_id int(10) UN
 | object_type varchar(15)
 | news_name   varchar(255)
 | description varchar(2000)
 | image   varchar(255)
 | begin_date  datetime
 | end_date    datetime
 | sticky_order    tinyint(4)
 | link_object_type    varchar(15)
 | status  varchar(15)
 | created_by  bigint(20) UN
 | modified_by bigint(20) UN
 | created_at  timestamp
 | updated_at  timestamp
*/

$factory('News', [
    'mall_id' => 'factory:Merchant',
    'news_name'  => $faker->sentence(3),
    'object_type'  => $faker->randomElement(['promotion', 'news']),
    'status'      => 'active',
    'begin_date'  => $faker->dateTimeBetween('-2 months'),
    'end_date'    => $faker->dateTimeBetween('+2 days', "+1 months"),
    'link_object_type'    => $faker->randomElement(['tenant', 'tenant_category']),
    'campaign_status_id' => 'factory:CampaignStatus',
    'is_all_gender' => 'Y',
    'is_all_age' => 'Y',
    'is_popup' => 'Y',
]);
