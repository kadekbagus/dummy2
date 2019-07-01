<?php
/**
 * Event listener for Gamification related events.
 *
 * @author Zamroni <zamroni@dominopos.com>
 * @author Budi <budi@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Events\Listeners\Gamification\PointRewarder;
use Orbit\Events\Listeners\Gamification\OneTimeReward;
use Orbit\Events\Listeners\Gamification\ThrottledRewarder;
use Orbit\Events\Listeners\Gamification\ActivatedUserRewarder;

/**
 * Listen on:    `orbit.user.activation.success`
 * Purpose:      add user game point when user successfully activated/verify account
 *
 * @param User $user - Instance of activated user
 */
Event::listen(
    'orbit.user.activation.success',
    //reward active user only
    new ActivatedUserRewarder(
        new OneTimeReward(new PointRewarder('sign_up'))
    )
);

/**
 * Listen on:    `'orbit.rating.postrating.success'`
 * Purpose:      add user game point when user successfully post review
 *
 * @param User $user - Instance of activated user
 * @param object $data - additional data about object being reviewed
 */
Event::listen(
    'orbit.rating.postrating.success',
    //reward active user only
    new ActivatedUserRewarder(
        new PointRewarder('review')
    )
);

/**
 * Listen on:    `'orbit.rating.postrating.reject'`
 * Purpose:      remove user game point when user review is rejected
 *
 * @param User $user - Instance of activated user
 * @param object $data - additional data about object being reviewed
 */
Event::listen(
    'orbit.rating.postrating.reject',
    //reward active user only
    new ActivatedUserRewarder(
        new PointRewarder('reject_review')
    )
);

/**
 * Listen on:    `'orbit.rating.postrating.rejectimage'`
 * Purpose:      remove user game point when user review is rejected and
 *              review contain previously approved image
 *
 * @param User $user - Instance of activated user
 * @param object $data - additional data about object being reviewed
 */
Event::listen(
    'orbit.rating.postrating.rejectimage',
    //reward active user only
    new ActivatedUserRewarder(
        new PointRewarder('reject_review_image')
    )
);

/**
 * Listen on:    `orbit.rating.postrating.approve.image`
 * Purpose:      add user game point when user review images approved, reward
 *              is given for first time one or more image approved.
 *              For additional images, next approval will not give user game point
 *
 * @param User $user - Instance of activated user
 * @param object $data - additional data about object being reviewed
 */
Event::listen(
    'orbit.rating.postrating.approve.image',
    //reward active user only
    new ActivatedUserRewarder(
        new OneTimeReward(new PointRewarder('review_image'))
    )
);

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

    Event::fire('orbit.rating.postrating.success', [$user, $body]);
});

/**
 * Listen on:    `orbit.purchase.pulsa.success`
 * Purpose:      Add user game point when user successfully purchase pulsa
 *
 * @param User $user - Instance of activated user
 * @param mixed $data - additional related data about pulsa
 */
Event::listen(
    'orbit.purchase.pulsa.success',
    //reward active user only
    new ActivatedUserRewarder(new PointRewarder('purchase'))
);

/**
 * Listen on:    `orbit.purchase.coupon.success`
 * Purpose:      Add user game point when user successfully purchase coupon
 *
 * @param User $user - Instance of activated user
 * @param mixed $data - additional related data about coupon
 */
Event::listen(
    'orbit.purchase.coupon.success',
    //reward active user only
    new ActivatedUserRewarder(new PointRewarder('purchase'))
);

/**
 * Listen on:    `orbit.redeem.coupon.success`
 * Purpose:      Add user game point when user successfully redeem normal coupon
 *
 * @param User $user - Instance of activated user
 * @param mixed $data - additional related data about coupon
 */
Event::listen(
    'orbit.redeem.coupon.success',
    //reward active user only
    new ActivatedUserRewarder(
        new ThrottledRewarder(
            new PointRewarder('purchase')
        )
    )
);

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
    Log::info('orbit.coupon.postissuedcoupon.after.commit');
    Event::fire('orbit.redeem.coupon.success', [$user, $data]);
});

/**
 * Listen on:    `orbit.follow.postfollow.success`
 * Purpose:      Add user game point when user successfully follow store or mall
 *
 * @param User $user - Instance of activated user
 * @param mixed $data - additional related data about store or mall
 */
Event::listen(
    'orbit.follow.postfollow.success',
    //reward active user only
    new ActivatedUserRewarder(
        new PointRewarder('follow')
    )
);

/**
 * Listen on:    `orbit.follow.postunfollow.success`
 * Purpose:      Reduce user game point when user successfully unfollow store or mall
 *
 * @param User $user - Instance of activated user
 * @param mixed $data - additional related data about store or mall
 */
Event::listen(
    'orbit.follow.postunfollow.success',
    //reward active user only
    new ActivatedUserRewarder(
        new PointRewarder('unfollow')
    )
);

/**
 * Listen on:    `orbit.share.post.success`
 * Purpose:      add user game point when user share via AddThis
 *
 * @param User $user - Instance of activated user
 * @param mixed $data - additional related data about share
 */
Event::listen(
    'orbit.share.post.success',
    //reward active user only
    new ActivatedUserRewarder(
        new OneTimeReward(new PointRewarder('share'))
    )
);
