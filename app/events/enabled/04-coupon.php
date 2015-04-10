<?php
/**
 * Event listener for Coupon related events.
 *
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:    `orbit.coupon.postnewcoupon.after.save`
 * Purpose:      Handle file upload on coupon creation
 *
 * @author Tian <tian@dominopos.com>
 *
 * @param CouponAPIController $controller - The instance of the CouponAPIController or its subclass
 * @param Coupon $coupon - Instance of object Coupon
 */
Event::listen('orbit.coupon.postnewcoupon.after.save', function($controller, $coupon)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    $_POST['promotion_id'] = $coupon->promotion_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('coupon.new')
                                   ->postUploadCouponImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['promotion_id']);

    $coupon->setRelation('media', $response->data);
    $coupon->media = $response->data;
    $coupon->image = $response->data[0]->path;
});

/**
 * Listen on:    `orbit.coupon.postupdatecoupon.after.save`
 * Purpose:      Handle file upload on coupon update
 *
 * @author Tian <tian@dominopos.com>
 *
 * @param CouponAPIController $controller - The instance of the CouponAPIController or its subclass
 * @param Coupon $coupon - Instance of object Coupon
 */
Event::listen('orbit.coupon.postupdatecoupon.after.save', function($controller, $coupon)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('coupon.update')
                                   ->postUploadCouponImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    $coupon->load('media');
    $coupon->image = $response->data[0]->path;
});
