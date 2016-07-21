<?php

/*
 | Table: lucky_draw_announcements
 | Columns:
 | lucky_draw_announcement_id	char(16) PK
 | lucky_draw_id	char(16)
 | title	varchar(255)
 | description	varchar(2000)
 | status	varchar(15)
 | created_by	char(16)
 | modified_by	char(16)
 | created_at	timestamp
 | updated_at	timestamp
 | blasted_at	datetime
 */

$factory('LuckyDrawAnnouncement', [
	'lucky_draw_id' => 'factory:LuckyDraw',
	'title'         => $faker->word,
	'description'   => $faker->word,
	'status'        => 'active',
]);