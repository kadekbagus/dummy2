<?php

/*
 | Table: orb_widget_translations
 | Columns:
 | widget_translation_id   char(16)
 | widget_id   char(16)
 | merchant_language_id    char(16)
 | widget_slogan   varchar(255)
 | status  varchar(15)
 | created_at  timestamp
 | updated_at  timestamp
 | created_by  char(16)
 | modified_by char(16)
*/

$factory('WidgetTranslation', [
    'widget_id'   => 'factory:Widget',
    'merchant_language_id' => 'factory:MerchantLanguage',
    'widget_slogan' => $faker->word,
    'status' => 'active',
]);