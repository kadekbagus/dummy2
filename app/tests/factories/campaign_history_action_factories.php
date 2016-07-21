<?php
/*
 | Table: orb_campaign_history_actions
 | Columns:
 | campaign_history_action_id  char(16) PK
 | action_name varchar(50)
 | created_by  char(16)
 | modified_by char(16)
 | created_at  timestamp
 | updated_at  timestamp
*/

$factory('CampaignHistoryAction', [
    'action_name' => $faker->randomElement([
                        'add_tenant',
                        'delete_tenant',
                        'activate',
                        'deactivate',
                        'change_base_price',
                    ]),
]);
