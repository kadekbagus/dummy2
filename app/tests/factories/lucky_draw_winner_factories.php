<?php

/*
 | lucky_draw_winner_id    char(16) PK
 | lucky_draw_id   char(16)
 | lucky_draw_winner_code  varchar(50)
 | position    tinyint(4)
 | lucky_draw_number_id    char(16)
 | lucky_draw_prize_id char(16)
 | status  varchar(15)
 | created_by  char(16)
 | modified_by char(16)
 | created_at  timestamp
 | updated_at  timestamp
 */

$factory('LuckyDrawWinner', [
    'lucky_draw_id' => 'factory:LuckyDraw',
    'lucky_draw_winner_code' => $faker->word,
    'position' => $faker->randomNumber(5),
    'lucky_draw_number_id' => 'factory:LuckyDrawNumber',
    'lucky_draw_prize_id' => 'factory:LuckyDrawPrize',
    'status' => 'active'
]);