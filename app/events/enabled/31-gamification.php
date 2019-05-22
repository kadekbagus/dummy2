<?php
/**
 * Event listener for Gamification related events.
 *
 * @author Zamroni <zamroni@dominopos.com>
 * @author Budi <budi@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Events\Listeners\Gamification\PointRewarder;

/**
 * Listen on:    `orbit.user.activation.success`
 * Purpose:      add user game point when user successfully activated/verify account
 *
 * @param User $user - Instance of activated user
 */
Event::listen('orbit.user.activation.success', new PointRewarder('sign_up'));

/**
 * Listen on:    `orbit.rating.postrating.without.image`
 * Purpose:      add user game point when user successfully post review without image
 *
 * @param User $user - Instance of activated user
 * @param object $data - additional data about object being reviewed
 */
Event::listen('orbit.rating.postrating.without.image', new PointRewarder('review'));

/**
 * Listen on:    `orbit.rating.postrating.with.image`
 * Purpose:      add user game point when user successfully post review with image
 *
 * @param User $user - Instance of activated user
 * @param object $data - additional data about object being reviewed
 */
Event::listen('orbit.rating.postrating.with.image', new PointRewarder('review_image'));

/**
 * Listen on:    `'orbit.rating.postrating.after.commit'`
 * Purpose:      add user game point when user successfully post review
 *
 * @param User $user - Instance of activated user
 */

Event::listen('orbit.rating.postrating.after.commit', function($ctrl, $body, $user) {
    if (isset($body['country']) && !isset($body['country_id'])) {
        $body['country_id'] = $body['country'];
    }

    if (isset($body['images'])) {
        Event::fire('orbit.rating.postrating.with.image', [$user, $body]);
    } else {
        Event::fire('orbit.rating.postrating.without.image', [$user, $body]);
    }
});

/**
 * Listen on:    `orbit.purchase.pulsa.success`
 * Purpose:      Add user game point when user successfully purchase pulsa
 *
 * @param User $user - Instance of activated user
 * @param mixed $data - additional related data about pulsa
 */
Event::listen('orbit.purchase.pulsa.success', new PointRewarder('purchase'));

/**
 * Listen on:    `orbit.purchase.coupon.success`
 * Purpose:      Add user game point when user successfully purchase coupon
 *
 * @param User $user - Instance of activated user
 * @param mixed $data - additional related data about coupon
 */
Event::listen('orbit.purchase.coupon.success', new PointRewarder('purchase'));

/**
 * Listen on:    `orbit.redeem.coupon.success`
 * Purpose:      Add user game point when user successfully redeem normal coupon
 *
 * @param User $user - Instance of activated user
 * @param mixed $data - additional related data about coupon
 */
Event::listen('orbit.redeem.coupon.success', new PointRewarder('purchase'));

/**
 * Listen on:    `orbit.coupon.postissuedcoupon.after.commit`
 * Purpose:      Add user game point when user successfully redeem normal coupon
 *
 * @param $ctrl - CouponAPIController instance
 * @param object $issuedCoupon - issued coupon
 * @param User $user - Instance of activated user
 * @param mixed $data - additional related data about coupon
 */
Event::listen('orbit.coupon.postissuedcoupon.after.commit', function($ctrl, $issuedCoupon, $user, $data) {
    Event::fire('orbit.redeem.coupon.success', [$user, $data]);
});

/**
 * Listen on:    `orbit.follow.postfollow.success`
 * Purpose:      Add user game point when user successfully follow store or mall
 *
 * @param User $user - Instance of activated user
 * @param mixed $data - additional related data about store or mall
 */
Event::listen('orbit.follow.postfollow.success', new PointRewarder('follow'));
