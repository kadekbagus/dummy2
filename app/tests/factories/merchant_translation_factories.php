<?php

/*
 | Table: orbs_merchant_translations
 | Columns:
 | merchant_translation_id  char(16) PK
 | merchant_id  char(16)
 | merchant_language_id char(16)
 | name varchar(100)
 | description  text
 | ticket_header    text
 | ticket_footer    text
 | status   varchar(15)
 | created_at   timestamp
 | updated_at   timestamp
 | created_by   bigint(20) UN
 | modified_by  bigint(20) UN
*/

$factory('MerchantTranslation', [
    'merchant_id' => 'factory:Tenant',
    'merchant_language_id' => 'factory:MerchantLanguage',
    'name' => $faker->word(),
    'description' => $faker->sentence(5),
    'status' => 'active',
]);
