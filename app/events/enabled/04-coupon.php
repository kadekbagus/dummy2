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

/**
 * Listen on:    `orbit.coupon.after.translation.save`
 * Purpose:      Handle file upload on coupon with language translation
 *
 * @author Ahmad Anshori <ahmad@dominopos.com>
 *
 * @param CouponAPIController $controller
 * @param CouponTranslations $coupon_translations
 */
Event::listen('orbit.coupon.after.translation.save', function($controller, $coupon_translations)
{
    $image_id = $coupon_translations->merchant_language_id;

    $files = OrbitInput::files('image_translation_' . $image_id);
    if (! $files) {
        return;
    }

    $_POST['coupon_translation_id'] = $coupon_translations->coupon_translation_id;
    $_POST['promotion_id'] = $coupon_translations->promotion_id;
    $_POST['merchant_language_id'] = $coupon_translations->merchant_language_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('coupon.translations')
                                   ->postUploadCouponTranslationImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    unset($_POST['coupon_translation_id']);
    unset($_POST['coupon_id']);
    unset($_POST['merchant_language_id']);

    $coupon_translations->setRelation('media', $response->data);
    $coupon_translations->media = $response->data;
    $coupon_translations->image_translation = $response->data[0]->path;
});


/**
 * Listen on:    `orbit.coupon.postnewcoupon.after.commit`
 * Purpose:      Send email to marketing after create coupon
 *
 * @author kadek <kadek@dominopos.com>
 *
 * @param CouponAPIController $controller
 * @param Coupon $coupon
 */
Event::listen('orbit.coupon.postnewcoupon.after.commit', function($controller, $coupon)
{
    $timestamp = new DateTime($coupon->created_at);
    $date = $timestamp->format('d F Y H:i').' (UTC)';

    // Send email process to the queue
    Queue::push('Orbit\\Queue\\CampaignMail', [
        'campaignType'       => 'Coupon',
        'campaignName'       => $coupon->promotion_name,
        'pmpUser'            => $controller->api->user->username,
        'eventType'          => 'created',
        'date'               => $date,
        'campaignId'         => $coupon->promotion_id,
        'mode'               => 'create'
    ]);

});


/**
 * Listen on:    `orbit.coupon.postupdatecoupon.after.commit`
 * Purpose:      Send email to marketing after create coupon
 *
 * @author kadek <kadek@dominopos.com>
 *
 * @param CouponAPIController $controller
 * @param Coupon $coupon
 */
Event::listen('orbit.coupon.postupdatecoupon.after.commit', function($controller, $coupon, $temporaryContentId)
{
    $timestamp = new DateTime($coupon->updated_at);
    $date = $timestamp->format('d F Y H:i').' (UTC)';

    // Send email process to the queue
    Queue::push('Orbit\\Queue\\CampaignMail', [
        'campaignType'       => 'Coupon',
        'campaignName'       => $coupon->promotion_name,
        'pmpUser'            => $controller->api->user->username,
        'eventType'          => 'updated',
        'date'               => $date,
        'campaignId'         => $coupon->promotion_id,
        'temporaryContentId' => $temporaryContentId,
        'mode'               => 'update'
    ]);

});
