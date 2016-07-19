<?php
/*
 | Table: coupon_translations
 | Columns:
 | coupon_translation_id   char(16) PK
 | promotion_id    char(16)
 | merchant_language_id    char(16)
 | promotion_name  varchar(255)
 | description varchar(2000)
 | long_description    text
 | status  varchar(15)
 | created_by  bigint(20) UN
 | modified_by bigint(20) UN
 | created_at  timestamp
 | updated_at  timestamp
*/

$factory('CouponTranslation', [
    'promotion_id'         => 'factory:Coupon',
    'merchant_language_id' => 'factory:Language',
    'promotion_name'       => $faker->sentence(3),
    'description'          => $faker->sentence(5),
    'status'               => 'active',
]);
