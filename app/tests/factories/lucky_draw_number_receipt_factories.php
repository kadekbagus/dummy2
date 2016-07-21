<?php

/*
 | lucky_draw_number_receipt_id char(16) PK
 | lucky_draw_number_id char(16)
 | lucky_draw_receipt_id    char(16)
 */

$factory('LuckyDrawNumberReceipt', [
    'lucky_draw_number_id' => 'factory:LuckyDrawNumber',
    'lucky_draw_receipt_id' => 'factory:LuckyDrawReceipt'
]);