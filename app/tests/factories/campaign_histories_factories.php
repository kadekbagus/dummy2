<?php
/*
 | Table: orb_campaign_histories
 | Columns:
 | campaign_history_id char(16) PK
 | campaign_type   varchar(50)
 | campaign_id char(16)
 | campaign_external_value char(16)
 | campaign_history_action_id  char(16)
 | number_active_tenants   smallint(5) UN
 | campaign_cost   decimal(12,2)
 | created_by  char(16)
 | modified_by char(16)
 | created_at  timestamp
 | updated_at  timestamp
*/

$factory('CampaignHistory', [
    'campaign_type'              => 'news',
    'campaign_id'                => 'factory:News',
    'campaign_external_value'    => 'factory:Tenant',
    'campaign_history_action_id' => 'factory:CampaignHistoryAction',
    'number_active_tenants'      => $faker->numberBetween(1, 20),
    'campaign_cost'              => 1000,
]);
