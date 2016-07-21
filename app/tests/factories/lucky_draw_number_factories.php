<?php

/*
 | Table: orbs_lucky_draw_numbers
 | Columns:
 | lucky_draw_number_id	char(16) PK
 | lucky_draw_id	char(16)
 | user_id	char(16)
 | hash	varchar(40)
 | lucky_draw_number_code	varchar(50)
 | issued_date	datetime
 | created_by	char(16)
 | modified_by	char(16)
 | status	varchar(15)
 | created_at	timestamp
 | updated_at	timestamp
 */

$factory('LuckyDrawNumber', [
	'lucky_draw_id' => 'factory:LuckyDraw',
	'hash' => $faker->randomNumber(),
	'lucky_draw_number_code' => $faker->randomNumber(),
	'status' => 'active',
]);