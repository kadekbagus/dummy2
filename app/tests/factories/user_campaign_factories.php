<?php
/*
 | Table: orb_user_campaign
 | Columns:
 | user_campaign_id    char(16) PK
 | user_id char(16)
 | campaign_id char(16)
 | campaign_type   varchar(50)
 | created_at  timestamp
 | updated_at  timestamp
*/

$factory('UserCampaign', [
    'user_id' => 'factory:User',
    'campaign_id' => 'factory:News',
    'campaign_type' => 'factory:News',
]);

$factory('UserCampaign', 'user_campaign_news', [
    'user_id' => 'factory:User',
    'campaign_id' => 'factory:News',
    'campaign_type' => 'news'
]);

$factory('UserCampaign', 'user_campaign_promotion', [
    'user_id' => 'factory:User',
    'campaign_id' => 'factory:News',
    'campaign_type' => 'promotion'
]);

$factory('UserCampaign', 'user_campaign_coupon', [
    'user_id' => 'factory:User',
    'campaign_id' => 'factory:Coupon',
    'campaign_type' => 'coupon'
]);
