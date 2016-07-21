<?php

/*
 | lucky_draw_prize_id char(16) PK
 | lucky_draw_id   char(16)
 | prize_name  varchar(255)
 | order   int(11)
 | winner_number   int(11)
 | status  varchar(15)
 | created_by  char(16)
 | modified_by char(16)
 | created_at  timestamp
 | updated_at  timestamp
 */

$factory('LuckyDrawPrize', [
    'lucky_draw_id' => 'factory:LuckyDraw',
    'prize_name' => $faker->word,
    'order' => $faker->randomNumber(),
    'winner_number' => $faker->randomNumber(),
    'status' => 'active'
]);