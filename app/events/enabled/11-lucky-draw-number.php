<?php

/**
 * Listen on:       `orbit.user.postupdatemembership.after.commit`
 *   Purpose:       Handle events after membership being updated
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @param LoginAPIController $controller - The instance of the LoginAPIController or its subclass
 * @param string $hash - Receipt Group hash number
 * @param LuckyDraw $luckyDraw - Instance of object LuckyDraw
 * @param User $customer - Instance of object User
 * @param int $retailerId - Retailer/Mall ID
 */
Event::listen('orbit.luckydrawnumbercs.postnewluckydrawnumbercs.after.commit', function($controller, $hash, $luckyDraw, $customer, $retailerId)
{
    // Notify the queueing system
    Queue::push('Orbit\\Queue\\Notifier\\LuckyDrawNumberNotifier', [
        'hash' => $hash,
        'lucky_draw_id' => $luckyDraw->lucky_draw_id,
        'user_id' => $customer->user_id,
        'retailer_id' => $retailerId
    ]);
});