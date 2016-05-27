<?php
/*
  | Table: orb_merchant_languages
  | Columns:
  | merchant_language_id    char(16) PK
  | language_id char(16)
  | merchant_id char(16)
  | created_at  timestamp
  | updated_at  timestamp
  | status  varchar(15)
*/
$factory('MerchantLanguage', [
    'language_id' => 'factory:Language',
    'merchant_id' => 'factory:Mall',
    'status'      => 'active'
]);