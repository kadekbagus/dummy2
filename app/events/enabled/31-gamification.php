<?php
/**
 * Event listener for Gamification related events.
 *
 * @author zamroni<zamroni@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Events\Listeners\Gamification\PointRewarder;

/**
 * Listen on:    `orbit.user.activation.success`
 * Purpose:      Handle user activation event
 *
 * @param User $user - Instance of activated user
 */

// successfully activate account
Event::listen('orbit.user.activation.success', new PointRewarder('sign_up'));
