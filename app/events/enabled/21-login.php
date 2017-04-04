<?php
/**
 * Event listener for Login related events
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Helper\PromotionalEvent\PromotionalEventProcessor;

/**
 * Listen on:       `orbit.login.after.success`
 *
 * @author Ahmad <ahmad@dominopos.com>
 * @param $userId - newly registered user ID
 * @param $rewardId - promotional event ID
 * @param $rewardType - ('coupon', 'promotion', 'news')
 * @param $language - ('en', 'id', etc)
 */
Event::listen('orbit.login.after.success', function($userId, $rewardId, $rewardType, $language)
{
    if (! is_null($rewardId) && ! is_null($rewardType)) {
        $updateReward = PromotionalEventProcessor::create($userId, $rewardId, $rewardType, $language, 'existing_user')->insertRewardCode();
    }
});

