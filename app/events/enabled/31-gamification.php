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

/**
 * Listen on:    `orbit.rating.postrating.with.image`
 * Purpose:      Handle event when user successfully post review without image
 *
 * @param User $user - Instance of activated user
 * @param object $data - additional data about object being reviewed
 */
Event::listen('orbit.rating.postrating.with.image', new PointRewarder('review'));

/**
 * Listen on:    `orbit.rating.postrating.without.image`
 * Purpose:      Handle event when user successfully post review without image
 *
 * @param User $user - Instance of activated user
 * @param object $data - additional data about object being reviewed
 */
Event::listen('orbit.rating.postrating.without.image', new PointRewarder('review_image'));

/**
 * Listen on:    `'orbit.rating.postrating.after.commit'`
 * Purpose:      Handle event when user successfully post review
 *
 * @param User $user - Instance of activated user
 */

Event::listen('orbit.rating.postrating.after.commit', function($ctrl, $body, $user) {
    if (isset($body['images'])) {
        Event::fire('orbit.rating.postrating.with.image', [$user, $body]);
    } else {
        Event::fire('orbit.rating.postrating.without.image', [$user, $body]);
    }
});
