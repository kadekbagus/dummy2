<?php
/*
 | Table: orb_campaign_status
 | Columns:
 | campaign_status_id  char(16) PK
 | campaign_status_name    varchar(50)
 | order   tinyint(4)
 | created_at  timestamp
 | updated_at  timestamp
 */
$factory('CampaignStatus', [
    'campaign_status_name'       => $faker->randomElement([
                                        "expired",
                                        "not started",
                                        "ongoing",
                                        "paused",
                                        "stopped"
                                    ]),
    'order'       => $faker->numberBetween($min = 1, $max = 5)
    ]
);

$factory('CampaignStatus', 'campaign_paused', [
    'campaign_status_name'       => 'paused',
    'order'       => 4
    ]
);

$factory('CampaignStatus', 'campaign_expired', [
    'campaign_status_name'       => 'expired',
    'order'       => 1
    ]
);