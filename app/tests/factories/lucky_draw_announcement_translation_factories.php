<?php

/*
 | lucky_draw_announcement_translation_id  char(16) PK
 | lucky_draw_announcement_id  char(16)
 | merchant_language_id    char(16)
 | title   varchar(255)
 | description varchar(2000)
 | status  varchar(15)
 | created_by  char(16)
 | modified_by char(16)
 | created_at  timestamp
 | updated_at  timestamp
*/

$factory('LuckyDrawAnnouncementTranslation', [
    'lucky_draw_announcement_id' => 'factory:LuckyDrawAnnouncement',
    'merchant_language_id' => 'factory:MerchantLanguage',
    'title' => $faker->sentence(3),
    'description' => $faker->sentence(5),
    'status' => 'active'
]);