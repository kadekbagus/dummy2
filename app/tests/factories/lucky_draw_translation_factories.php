<?php

/*
 | lucky_draw_translation_id	char(16) PK
 | lucky_draw_id	char(16)
 | merchant_language_id	char(16)
 | lucky_draw_name	varchar(255)
 | description	varchar(2000)
 | status	varchar(15)
 | created_by	char(16)
 | modified_by	char(16)
 | created_at	timestamp
 | updated_at	timestamp
*/

$factory('LuckyDrawTranslation', [
	'lucky_draw_id'        => 'factory:LuckyDraw',
	'merchant_language_id' => 'factory:MerchantLanguage',
	'lucky_draw_name'      => $faker->sentence(3),
	'description'          => $faker->sentence(5),
	'status'               => 'active'
]);