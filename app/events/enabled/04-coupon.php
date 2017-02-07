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

    // queue for data amazon s3
    $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);

    if ($usingCdn) {
        $bucketName = Config::get('orbit.cdn.providers.S3.bucket_name', '');
        $queueName = Config::get('orbit.cdn.queue_name', 'cdn_upload');
        $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadNewQueue';
        if ($response->data['extras']->isUpdate) {
            $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadUpdateQueue';
        }

        Queue::push($queueFile, [
            'object_id'     => $coupon->promotion_id,
            'media_name_id' => $response->data['extras']->mediaNameId,
            'old_path'      => $response->data['extras']->oldPath,
            'es_type'       => 'coupon',
            'es_id'         => $coupon->promotion_id,
            'bucket_name'   => $bucketName
        ], $queueName);
    }
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

    // queue for data amazon s3
    $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);

    if ($usingCdn) {
        $bucketName = Config::get('orbit.cdn.providers.S3.bucket_name', '');
        $queueName = Config::get('orbit.cdn.queue_name', 'cdn_upload');
        $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadNewQueue';
        if ($response->data['extras']->isUpdate) {
            $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadUpdateQueue';
        }

        Queue::push($queueFile, [
            'object_id'     => $coupon->promotion_id,
            'media_name_id' => $response->data['extras']->mediaNameId,
            'old_path'      => $response->data['extras']->oldPath,
            'es_type'       => 'coupon',
            'es_id'         => $coupon->promotion_id,
            'bucket_name'   => $bucketName
        ], $queueName);
    }
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

    // queue for data amazon s3
    $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);

    if ($usingCdn) {
        $bucketName = Config::get('orbit.cdn.providers.S3.bucket_name', '');
        $queueName = Config::get('orbit.cdn.queue_name', 'cdn_upload');
        $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadNewQueue';
        if ($response->data['extras']->isUpdate) {
            $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadUpdateQueue';
        }

        Queue::push($queueFile, [
            'object_id'     => $coupon_translations->coupon_translation_id,
            'media_name_id' => $response->data['extras']->mediaNameId,
            'old_path'      => $response->data['extras']->oldPath,
            'es_type'       => 'coupon',
            'es_id'         => $coupon_translations->promotion_id,
            'bucket_name'   => $bucketName
        ], $queueName);
    }
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
 * @author irianto <irianto@dominopos.com>
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

    // check coupon before update elasticsearch
    $prefix = DB::getTablePrefix();
    $coupon = Coupon::select(
                DB::raw("
                    {$prefix}promotions.promotion_id,
                    CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                        THEN {$prefix}campaign_status.campaign_status_name
                        ELSE (CASE WHEN {$prefix}promotions.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                        FROM {$prefix}promotion_retailer opt
                                                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                        WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                                                    )
                        THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END)
                    END AS campaign_status,
                    COUNT({$prefix}issued_coupons.issued_coupon_id) as available
                "))
                ->join('promotion_rules', 'promotion_rules.promotion_id', '=', 'promotions.promotion_id')
                ->join('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                ->leftJoin('issued_coupons', function($q) {
                    $q->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id')
                        ->where('issued_coupons.status', '=', "available");
                })
                ->where('promotions.promotion_id', $coupon->promotion_id)
                ->whereRaw("{$prefix}promotions.is_coupon = 'Y'")
                ->whereRaw("{$prefix}promotion_rules.rule_type != 'blast_via_sms'")
                ->first();

    if (! empty($coupon)) {
        if ($coupon->campaign_status === 'stopped' || $coupon->campaign_status === 'expired' || $coupon->available === 0) {
            // Notify the queueing system to delete Elasticsearch document
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponDeleteQueue', [
                'coupon_id' => $coupon->promotion_id
            ]);

            // Notify the queueing system to update Elasticsearch suggestion document
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponSuggestionDeleteQueue', [
                'coupon_id' => $coupon->promotion_id
            ]);
        } else {
            // Notify the queueing system to update Elasticsearch document
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', [
                'coupon_id' => $coupon->promotion_id
            ]);
        }
    }

});


Event::listen('orbit.coupon.postaddtowallet.after.commit', function($controller, $coupon_id)
{
    // Notify the queueing system to update Elasticsearch document
    Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', [
        'coupon_id' => $coupon_id
    ]);

    // Delete coupon suggestion in index es when available coupon is empty
    $availableCoupons = IssuedCoupon:: select('issued_coupon_id')
        ->where('status', 'available')
        ->where('promotion_id', $coupon_id)
        ->first();

    if (empty($availableCoupons)) {
        Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponSuggestDeleteQueue', [
            'coupon_id' => $coupon_id
        ]);
    }

});