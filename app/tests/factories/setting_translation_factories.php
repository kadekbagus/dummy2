<?php

/*
 | Table: orbs_settings
 | Columns:
 | Table: orb_setting_translations
 | Columns:
 | setting_translation_id   char(16)
 | setting_id   char(16)
 | merchant_language_id char(16)
 | setting_value    varchar(255)
 | status   varchar(15)
 | created_at   timestamp
 | updated_at   timestamp
 | created_by   char(16)
 | modified_by  char(16)
*/

$factory('SettingTranslation', [
    'setting_id' => 'factory:Setting',
    'merchant_language_id' => 'factory:MerchantLanguage',
    'setting_value' => $faker->word,
    'status' => 'active',
]);