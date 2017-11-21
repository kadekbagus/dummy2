<?php
/**
 * Event listener for Coupon related events.
 *
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Orbit\Helper\Util\LandingPageUrlGenerator as LandingPageUrlGenerator;
use Carbon\Carbon as Carbon;
use Orbit\Helper\Util\CdnUrlGenerator;
use Orbit\Helper\OneSignal\OneSignal;

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
 * Listen on:    `orbit.coupon.after.header.translation.save`
 * Purpose:      Handle file upload on coupon with language translation
 *
 * @author Ahmad Anshori <ahmad@dominopos.com>
 *
 * @param CouponAPIController $controller
 * @param CouponTranslations $coupon_translations
 */
Event::listen('orbit.coupon.after.header.translation.save', function($controller, $coupon_translations)
{
    $image_id = $coupon_translations->merchant_language_id;

    $header_files = OrbitInput::files('header_image_translation_' . $image_id);
    if (! $header_files) {
        return;
    }

    $_POST['coupon_translation_id'] = $coupon_translations->coupon_translation_id;
    $_POST['promotion_id'] = $coupon_translations->promotion_id;
    $_POST['merchant_language_id'] = $coupon_translations->merchant_language_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('coupon.translations')
                                   ->postUploadCouponHeaderTranslationImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    unset($_POST['coupon_translation_id']);
    unset($_POST['coupon_id']);
    unset($_POST['merchant_language_id']);

    $coupon_translations->setRelation('mediaGrabHeader', $response->data);
    $coupon_translations->media = $response->data;
    $coupon_translations->header_image_translation = $response->data[0]->path;

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
 * Listen on:    `orbit.coupon.after.image1.translation.save`
 * Purpose:      Handle file upload on coupon with language translation
 *
 * @author Ahmad Anshori <ahmad@dominopos.com>
 *
 * @param CouponAPIController $controller
 * @param CouponTranslations $coupon_translations
 */
Event::listen('orbit.coupon.after.image1.translation.save', function($controller, $coupon_translations)
{
    $image_id = $coupon_translations->merchant_language_id;

    $header_files = OrbitInput::files('image1_translation_' . $image_id);
    if (! $header_files) {
        return;
    }

    $_POST['coupon_translation_id'] = $coupon_translations->coupon_translation_id;
    $_POST['promotion_id'] = $coupon_translations->promotion_id;
    $_POST['merchant_language_id'] = $coupon_translations->merchant_language_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('coupon.translations')
                                   ->postUploadCouponImage1TranslationImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    unset($_POST['coupon_translation_id']);
    unset($_POST['coupon_id']);
    unset($_POST['merchant_language_id']);

    $coupon_translations->setRelation('mediaGrabImage1', $response->data);
    $coupon_translations->media = $response->data;
    $coupon_translations->image1_translation = $response->data[0]->path;

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
    // update total available coupon
    $availableCoupons = IssuedCoupon::totalAvailable($coupon->promotion_id);

    $coupon = Coupon::findOnWriteConnection($coupon->promotion_id);
    $coupon->available = $availableCoupons;
    $coupon->save();

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

    // update total available coupon
    $availableCoupons = IssuedCoupon::totalAvailable($coupon->promotion_id);

    $coupon = Coupon::findOnWriteConnection($coupon->promotion_id);
    $coupon->available = $availableCoupons;
    $coupon->save();

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
                    COUNT({$prefix}issued_coupons.issued_coupon_id) as available,
                    {$prefix}promotions.is_visible
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
        if ($coupon->campaign_status === 'stopped' || $coupon->campaign_status === 'expired' || $coupon->available === 0 || $coupon->is_visible === 'N') {
            // Notify the queueing system to delete Elasticsearch document
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponDeleteQueue', [
                'coupon_id' => $coupon->promotion_id
            ]);

            // Notify the queueing system to update Elasticsearch suggestion document
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponSuggestionDeleteQueue', [
                'coupon_id' => $coupon->promotion_id
            ]);
        } else {
            if ($coupon->is_visible === 'Y') {
                // Notify the queueing system to update Elasticsearch document
                Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', [
                    'coupon_id' => $coupon->promotion_id
                ]);
            }
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
    $availableCoupons = IssuedCoupon::totalAvailable($coupon_id);

    $coupon = Coupon::findOnWriteConnection($coupon_id);
    $coupon->available = $availableCoupons;
    $coupon->save();

    if ($availableCoupons === 0) {
        Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponSuggestionDeleteQueue', [
            'coupon_id' => $coupon_id
        ]);
    }

});


/**
 * Listen on:    `orbit.coupon.postupdatecoupon-storenotificationupdate.after.commit`
 * Purpose:      Handle push and inapps notification
 * @author firmansyah <firmansyah@dominopos.com>
 *
 * @param CouponAPIController $controller - The instance of the CouponAPIController or its subclass
 * @param News $coupon - Instance of object News
 */
Event::listen('orbit.coupon.postupdatecoupon-storenotificationupdate.after.commit', function($controller, $updatedcoupon)
{
    //Check date and status
    $timezone = 'Asia/Jakarta'; // now with jakarta timezone
    $timestamp = date("Y-m-d H:i:s");
    $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
    $dateTime = $date->toDateTimeString();
    $dateTimeNow = $date->setTimezone('Asia/Jakarta')->toDateTimeString();

    $mongoConfig = Config::get('database.mongodb');
    $mongoClient = MongoClient::create($mongoConfig);
    $oneSignalConfig = Config::get('orbit.vendor_push_notification.onesignal');

    if ($updatedcoupon->status === 'active') {

        // check existing notification
        $objectType = 'coupon';

        $queryStringStoreObject = [
            'object_id' => $updatedcoupon->promotion_id,
            'object_type' => $objectType,
        ];

        $storeObjectNotifications = $mongoClient->setQueryString($queryStringStoreObject)
                                                ->setEndPoint('store-object-notifications')
                                                ->request('GET');

        $storeNotification = null;

        $couponMain = Coupon::join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                            ->join('languages as default_languages', DB::raw('default_languages.name'), '=', 'campaign_account.mobile_default_language')
                            ->where('promotions.promotion_id', '=', $updatedcoupon->promotion_id);

        $langCoupon = $couponMain->select(DB::raw('default_languages.name as default_language_name'))->first();
        $defaultLangName = $langCoupon->default_language_name;

        if (! empty($storeObjectNotifications->data->records)) {
            $storeNotification = $storeObjectNotifications->data->records[0];
        } else {
            $_coupon = $couponMain->select('promotions.*',
                                      DB::raw('default_languages.name as default_language_name'),
                                      DB::raw('default_languages.language_id as default_language_id')
                                 )
                                 ->with('translations.media')
                                 ->first();

            $launchUrl = LandingPageUrlGenerator::create('coupon', $_coupon->promotion_id, $_coupon->promotion_name)->generateUrl();
            $attachmentPath = null;
            $attachmentRealPath = null;
            $cdnUrl = null;
            $cdnBucketName = null;
            $mimeType = null;
            $headings = new stdClass();
            $contents = new stdClass();

            // get heading, content, and image
            foreach ($_coupon->translations as $translation) {
                $languageName = $translation['name'];
                if (! empty($translation['promotion_name'])) {
                    $headings->$languageName = $translation['promotion_name'];
                    $contents->$languageName = $translation['description'];
                }

                if ($translation['merchant_language_id'] === $_coupon->default_language_id) {
                    if (! empty($translation->media)) {
                        foreach ($translation->media as $media) {
                            if ($media['media_name_long'] === 'coupon_translation_image_orig') {
                                $attachmentPath = $media['path'];
                                $attachmentRealPath = $media['realpath'];
                                $cdnUrl = $media['cdn_url'];
                                $cdnBucketName = $media['cdn_bucket_name'];
                                $mimeType = $media['mime_type'];
                            }
                        }
                    }
                }
            }

            // get user_ids and tokens
            $tenantIds = null;
            if (count($updatedcoupon['tenants']) > 0) {
                foreach ($updatedcoupon['tenants'] as $key => $tenant) {
                    if ($tenant->object_type === 'retailer') {
                        $tenantIds[] = $tenant->merchant_id;
                    }
                }
            }

            $queryStringUserFollow = [
                'object_id'   => $tenantIds,
                'object_type' => 'store'
            ];

            $userFollows = $mongoClient->setQueryString($queryStringUserFollow)
                                    ->setEndPoint('user-id-follows')
                                    ->request('GET');

            $userIds = null;
            $notificationToken = array();
            if ($userFollows->data->returned_records > 0) {
                $userIds = $userFollows->data->records;
                $queryStringUserNotifToken['user_ids'] = $userIds;

                $notificationTokens = $mongoClient->setQueryString($queryStringUserNotifToken)
                                    ->setEndPoint('user-notification-tokens')
                                    ->request('GET');

                if ($notificationTokens->data->total_records > 0) {
                    foreach ($notificationTokens->data->records as $key => $val) {
                        $notificationToken[] = $val->notification_token;
                    }
                }
            }

            // save to notifications collection in mongodb
            $dataNotification = [
                'title' => $_coupon->promotion_name,
                'launch_url' => $launchUrl,
                'attachment_path' => $attachmentPath,
                'attachment_realpath' => $attachmentRealPath,
                'cdn_url' => $cdnUrl,
                'cdn_bucket_name' => $cdnBucketName,
                'default_language' => $_coupon->default_language_name,
                'headings' => $headings,
                'contents' => $contents,
                'type' => $objectType,
                'status' => 'pending',
                'sent_at' => null,
                'notification_tokens' => json_encode($notificationToken),
                'user_ids' => json_encode($userIds),
                'vendor_notification_id' => null,
                'vendor_type' => Config::get('orbit.vendor_push_notification.default'),
                'is_automatic' => true,
                'mime_type' => $mimeType,
                'target_audience_ids' => null,
                'created_at' => $dateTime
            ];

            $notification = $mongoClient->setFormParam($dataNotification)
                                        ->setEndPoint('notifications')
                                        ->request('POST');
            $notificationId = $notification->data->_id;

            // save to store_object_notifications collection in mongodb
            $bodyStoreObjectNotifications = [
                'notification' => $notification->data,
                'object_id' => $_coupon->promotion_id,
                'object_type' => $objectType,
                'user_ids' => json_encode($userIds),
                'token' => json_encode($notificationToken),
                'status' => 'pending',
                'start_date' => $_coupon->begin_date,
                'created_at' => $dateTime
            ];

            $storeObjectNotif = $mongoClient->setFormParam($bodyStoreObjectNotifications)
                                            ->setEndPoint('store-object-notifications')
                                            ->request('POST');

            $storeNotification = $storeObjectNotif->data;
        }

        // sent notification if campaign already started
        if (($dateTimeNow >= $updatedcoupon->begin_date) && ($storeNotification->status === 'pending')) {
            // send to onesignal
            if (! empty($storeNotification->notification->notification_tokens)) {
                $mongoNotifId = $storeNotification->notification->_id;
                $launchUrl = $storeNotification->notification->launch_url;
                $headings = $storeNotification->notification->headings;
                $contents = $storeNotification->notification->contents;
                $notificationTokens = $storeNotification->notification->notification_tokens;

                $cdnConfig = Config::get('orbit.cdn');
                $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');
                $localPath = (! empty($storeNotification->notification->attachment_path)) ? $storeNotification->notification->attachment_path : '';
                $cdnPath = (! empty($storeNotification->notification->cdn_url)) ? $storeNotification->notification->cdn_url : '';
                $imageUrl = $imgUrl->getImageUrl($localPath, $cdnPath);

                // add query string for activity recording
                $newUrl =  $launchUrl . '?notif_id=' . $mongoNotifId;

                // english is mandatory in onesignal, set en value with default language content
                if (empty($headings->en)) {
                    $headings->en = $headings->$defaultLangName;
                }

                if (empty($contents->en)) {
                    $contents->en = $contents->$defaultLangName;
                }

                if (count($notificationTokens) > 1500) {
                    $newToken = array();
                    $stopLoop = false;
                    $startLoop = 0;
                    $oneSignalId = array();
                    while ($stopLoop == false) {
                        $newToken = array_slice($notificationTokens, $startLoop, 1500);

                        if (empty($newToken)) {
                            $stopLoop =  true;
                            break;
                        }

                        $data = [
                            'headings'           => $headings,
                            'contents'           => $contents,
                            'url'                => $newUrl,
                            'include_player_ids' => $newToken,
                            'ios_attachments'    => $imageUrl,
                            'big_picture'        => $imageUrl,
                            'adm_big_picture'    => $imageUrl,
                            'chrome_big_picture' => $imageUrl,
                            'chrome_web_image'   => $imageUrl,
                        ];

                        $oneSignal = new OneSignal($oneSignalConfig);
                        $newNotif = $oneSignal->notifications->add($data);
                        $oneSignalId[] = $newNotif->id;

                        $startLoop = $startLoop + 1500;
                    }
                    $bodyUpdate['vendor_notification_id'] = $oneSignalId;
                } else {

                    $data = [
                        'headings'           => $headings,
                        'contents'           => $contents,
                        'url'                => $newUrl,
                        'include_player_ids' => $notificationTokens,
                        'ios_attachments'    => $imageUrl,
                        'big_picture'        => $imageUrl,
                        'adm_big_picture'    => $imageUrl,
                        'chrome_big_picture' => $imageUrl,
                        'chrome_web_image'   => $imageUrl,
                    ];

                    $oneSignal = new OneSignal($oneSignalConfig);
                    $newNotif = $oneSignal->notifications->add($data);
                    $bodyUpdate['vendor_notification_id'] = $newNotif->id;
                }
            }

            // send as inApps notification
            if (! empty($storeNotification->notification->user_ids)) {
                foreach ($storeNotification->notification->user_ids as $userId) {
                    $bodyInApps = [
                        'user_id'       => $userId,
                        'token'         => null,
                        'notifications' => $storeNotification->notification,
                        'send_status'   => 'sent',
                        'is_viewed'     => false,
                        'is_read'       => false,
                        'created_at'    => $dateTime,
                        'image_url'     => $imageUrl
                    ];

                    $inApps = $mongoClient->setFormParam($bodyInApps)
                                ->setEndPoint('user-notifications') // express endpoint
                                ->request('POST');
                }
            }

            // Update status in notification collection from pending to sent
            $bodyUpdate['sent_at'] = $dateTime;
            $bodyUpdate['_id'] = $mongoNotifId;
            $bodyUpdate['status'] = 'sent';

            $responseUpdate = $mongoClient->setFormParam($bodyUpdate)
                                        ->setEndPoint('notifications') // express endpoint
                                        ->request('PUT');

            // Update status in store-object-notifications collection from pending to sent
            $storeBodyUpdate['_id'] = $storeNotification->_id;
            $storeBodyUpdate['notification'] = $responseUpdate->data;
            $storeBodyUpdate['status'] = 'sent';

            $responseStoreUpdate = $mongoClient->setFormParam($storeBodyUpdate)
                                        ->setEndPoint('store-object-notifications') // express endpoint
                                        ->request('PUT');
        }
    }
});

Event::listen('orbit.coupon.postupdatecoupon-mallnotification.after.save', function($controller, $coupon)
{
    if ($coupon->status === 'active')
    {
        // check mall follower
        $timestamp = date("Y-m-d H:i:s");
        $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
        $dateTime = $date->toDateTimeString();
        $mongoConfig = Config::get('database.mongodb');
        $mongoClient = MongoClient::create($mongoConfig);
        $follower = null;
        $mallData = null;
        $malls = null;
        $headings = null;
        $contents = null;
        $userIds = null;
        $attachmentPath = null;
        $attachmentRealPath = null;
        $cdnUrl = null;
        $cdnBucketName = null;
        $notificationId = null;
        $tokens = null;

        $prefix = DB::getTablePrefix();
        $malls = Coupon::select(DB::raw("CASE WHEN {$prefix}merchants.object_type ='tenant' THEN {$prefix}merchants.parent_id
                                                ELSE {$prefix}merchants.merchant_id
                                        END as mall_id"))
                        ->excludeDeleted('promotions')
                        ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                        ->where('promotions.promotion_id', $coupon->promotion_id)
                        ->groupBy('mall_id')
                        ->get();

        if (!empty($malls))
        {
           foreach ($malls as $key => $value)
            {
                $queryString = [
                    'object_id'   => $value->mall_id,
                    'object_type' => 'mall'
                ];

                $userFollow = $mongoClient->setQueryString($queryString)
                                          ->setEndPoint('user-follows')
                                          ->request('GET');

                if (count($userFollow->data->records) !== 0)
                {
                    foreach ($userFollow->data->records as $key => $value) {
                        $follower[] = $value->user_id;
                    }
                    $mallData[] = $value->mall_id;
                }
            }
        }

        if (!empty($follower) && !empty($mallData))
        {
            // get user_ids and tokens
            $userIds = array_unique($follower);
            $tokenSearch = ['user_ids' => $userIds, 'notification_provider' => 'onesignal'];
            $tokenData = $mongoClient->setQueryString($tokenSearch)
                                     ->setEndPoint('user-notification-tokens')
                                     ->request('GET');

            if ($tokenData->data->total_records > 0) {
                foreach ($tokenData->data->records as $key => $value) {
                    $tokens[] = $value->notification_token;
                }
                $tokens = array_unique($tokens);
            }

            $_coupon = Coupon::select('promotions.*',
                                  DB::raw('default_languages.name as default_language_name'),
                                  DB::raw('default_languages.language_id as default_language_id')
                                 )
                         ->with('translations.media')
                         ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                         ->join('languages as default_languages', DB::raw('default_languages.name'), '=', 'campaign_account.mobile_default_language')
                         ->where('promotions.promotion_id', '=', $coupon->promotion_id)
                         ->first();

            $launchUrl = LandingPageUrlGenerator::create('coupon', $_coupon->promotion_id, $_coupon->promotion_name)->generateUrl();

            $headings = new stdClass();
            $contents = new stdClass();
            $attachmentPath = null;
            $attachmentRealPath = null;
            $cdnUrl = null;
            $cdnBucketName = null;
            $mimeType = null;

            foreach ($coupon->translations as $key => $value)
            {
                if (!empty($value->promotion_name) && !empty($value->description))
                {
                    $languageName = $value->name;
                    if (! empty($value->promotion_name)) {
                        $headings->$languageName = $value->promotion_name;
                        $contents->$languageName = $value->description;
                    }
                }
                if ($value->merchant_language_id === $_coupon->default_language_id)
                {
                    if (count($value->media) !==0)
                    {
                        foreach ($value->media as $key => $value_media)
                        {
                            if($value_media->media_name_long === 'coupon_translation_image_orig')
                            {
                                $attachmentPath = $value_media->file_name;
                                $attachmentRealPath = $value_media->path;
                                $cdnUrl = $value_media->cdn_url;
                                $cdnBucketName = $value_media->cdn_bucket_name;
                                $mimeType = $value_media->mime_type;
                            }
                        }
                    }
                }
            }


            $dataNotification = [
                'title' => $_coupon->promotion_name,
                'launch_url' => $launchUrl,
                'attachment_path' => $attachmentPath,
                'attachment_realpath' => $attachmentRealPath,
                'cdn_url' => $cdnUrl,
                'cdn_bucket_name' => $cdnBucketName,
                'default_language' => $_coupon->default_language_name,
                'headings' => $headings,
                'contents' => $contents,
                'type' => 'coupon',
                'status' => 'pending',
                'sent_at' => null,
                'notification_tokens' => json_encode($tokens),
                'user_ids' => json_encode($userIds),
                'vendor_notification_id' => null,
                'vendor_type' => 'onesignal',
                'is_automatic' => true,
                'mime_type' => $mimeType,
                'target_audience_ids' => null,
                'created_at' => $dateTime
            ];

            $dataNotificationCheck = [
                'title' => $_coupon->promotion_name,
                'launch_url' => $launchUrl,
                'type' => $_coupon->object_type,
                'status' => 'pending',
            ];

            $notification = $mongoClient->setQueryString($dataNotificationCheck)
                                         ->setEndPoint('notifications')
                                         ->request('GET');

            if (count($notification->data->records) === 0) {
                $notification = $mongoClient->setFormParam($dataNotification)
                                            ->setEndPoint('notifications')
                                            ->request('POST');
                $notificationId = $notification->data->_id;
            } else {
                $notificationId = $notification->data->records[0]->_id;
                $updateDataNotification = [
                    '_id' => $notificationId,
                    'notification_tokens' => json_encode($tokens),
                    'user_ids' => json_encode($userIds),
                ];

                $updateNotification = $mongoClient->setFormParam($updateDataNotification)
                                                      ->setEndPoint('notifications')
                                                      ->request('PUT');
            }

            // loop the mall again
            foreach ($mallData as $key => $mallvalue)
            {
                $queryString = [
                    'mall_id' => $mallvalue,
                    'status' => 'pending'
                ];

                $mallObjectNotif = $mongoClient->setQueryString($queryString)
                                          ->setEndPoint('mall-object-notifications')
                                          ->request('GET');

                if (count($mallObjectNotif->data->records) === 0)
                {
                    // insert data if not exist
                    $insertMallObjectNotification = [
                        'notification_ids' => (array)$notificationId,
                        'mall_id' => $mallvalue,
                        'user_ids' => $userIds,
                        'tokens' => $tokens,
                        'status' => 'pending',
                        'start_at' => null,
                        'created_at' => $dateTime
                    ];

                    $mallObjectNotification = $mongoClient->setFormParam($insertMallObjectNotification)
                                                          ->setEndPoint('mall-object-notifications')
                                                          ->request('POST');
                } else {
                    // update data if exist
                    $_tokens = null;
                    $_userIds = null;
                    $_notificationIds = $mallObjectNotif->data->records[0]->notification_ids;
                    $_userIds = $mallObjectNotif->data->records[0]->user_ids;
                    $_tokens = $mallObjectNotif->data->records[0]->tokens;
                    $_notificationIds[] = $notificationId;
                    if (!empty($userIds)) {
                        foreach ($userIds as $key => $uservalue) {
                            $_userIds[] = $uservalue;
                        }
                        $_userIds = array_unique($_userIds);
                    }
                    if (!empty($tokens)) {
                        foreach ($tokens as $key => $tokenvalue) {
                            $_tokens[] = $tokenvalue;
                        }
                        $_tokens = array_unique($_tokens);
                    }
                    $updateMallObjectNotification = [
                        '_id' => $mallObjectNotif->data->records[0]->_id,
                        'notification_ids' => array_unique($_notificationIds),
                        'mall_id' => $mallvalue,
                        'user_ids' => $_userIds,
                        'tokens' => $_tokens,
                    ];

                    $mallObjectNotification = $mongoClient->setFormParam($updateMallObjectNotification)
                                                          ->setEndPoint('mall-object-notifications')
                                                          ->request('PUT');
                }
            }
        }
    }
});