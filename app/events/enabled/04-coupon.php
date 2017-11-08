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
 * Listen on:    `orbit.coupon.pushnotofication.after.save`
 * Purpose:      Handle push and inapps notification
 * @author firmansyah <firmansyah@dominopos.com>
 *
 * @param CouponAPIController $controller - The instance of the CouponAPIController or its subclass
 * @param Coupon $coupon - Instance of object Coupon
 */
Event::listen('orbit.coupon.pushnotofication.after.commit', function($controller, $coupon, $defaultLangId)
{
    // Push Notification and In Apps notofication, Insert to store_object_notification

    // Get distinct user_id who follows the link to tenant
    $tenantIds = null;
    if (count($coupon['tenants']) > 0) {
        foreach ($coupon['tenants'] as $key => $tenant) {
            if ($tenant->object_type === 'retailer') {
                $tenantIds[] = $tenant->merchant_id;
            }
        }
    }

    if ($tenantIds != null) {
        $mongoConfig = Config::get('database.mongodb');

        $queryString['object_id'] = $tenantIds;
        $queryString['object_type'] = 'store';

        $mongoClient = MongoClient::create($mongoConfig);
        $endPoint = "user-id-follows";

        $mongoConfig = Config::get('database.mongodb');
        $userFollows = $mongoClient->setQueryString($queryString)
                                ->setEndPoint($endPoint)
                                ->request('GET');

        $objectType = 'coupon';
        if ($userFollows->data->returned_records > 0) {
            $launchUrl = LandingPageUrlGenerator::create($objectType, $coupon->promotion_id, $coupon->promotion_name)->generateUrl();
            $userIds = $userFollows->data->records;

            $queryStringUserNotifToken['user_ids'] = $userIds;
            $endPoint = "user-notification-tokens";
            $notificationTokens = $mongoClient->setQueryString($queryStringUserNotifToken)
                                ->setEndPoint($endPoint)
                                ->request('GET');

            $notificationToken = array();
            if ($notificationTokens->data->total_records > 0) {
                foreach ($notificationTokens->data->records as $key => $val) {
                    $notificationToken[] = $val->notification_token;
                }
            }

            // Get language
            $defaultLanguage = null;
            $defaultLanguageId = null;
            $language = language::where('language_id', $defaultLangId)->first();
            if (! empty($language)) {
                $defaultLanguage = $language->name;
                $defaultLanguageId = $language->language_id;
            }

            // Get news translation defaul language
            $headings = new stdClass();
            $contents = new stdClass();
            $couponTransaltions = CouponTranslation::where('promotion_id', $coupon->promotion_id)->get();

            if (! empty($couponTransaltions)) {
                foreach ($couponTransaltions as $key => $couponTransaltion) {
                    $language = language::where('language_id', $couponTransaltion->merchant_language_id)->first();
                    $languageName = $language->name;
                    $headings->$languageName = $couponTransaltion->promotion_name;
                    $contents->$languageName = $couponTransaltion->description;
                }
            }

            // Insert notofications
            $bodyNotifications = [
                'title'               => $coupon->promotion_name,
                'launch_url'          => $launchUrl,
                'attachment_url'      => $attachmentUrl,
                'default_language'    => $defaultLanguage,
                'headings'            => $headings,
                'contents'            => $contents,
                'type'                => 'coupon',
                'status'              => 'pending',
                'created_at'          => $coupon->created_at,
                'vendor_type'         => Config::get('orbit.vendor_push_notification.default'),
                'notification_tokens' => $notificationToken,
                'user_ids'            => $userIds,
                'target_audience_ids' => null,
            ];

            $responseNotofocations = $mongoClient->setFormParam($bodyNotifications)
                                    ->setEndPoint('notifications') // express endpoint
                                    ->request('POST');

            // Insert to Store Object Notifications Collections
            $token = '';
            $status = '';
            $bodyStoreObjectNotifications = [
                'notification' => $responseNotofocations->data,
                'object_id' => $coupon->promotion_id,
                'object_type' => $objectType,
                'user_ids' => $userIds,
                'token' => $notificationToken,
                'status' => 'pending',
                'start_date' => $coupon->begin_date,
                'created_at' => $coupon->created_at
            ];

            $inApps = $mongoClient->setFormParam($bodyStoreObjectNotifications)
                        ->setEndPoint('store-object-notifications')
                        ->request('POST');
        }

        // send as inApps notification
        if (! empty($userIds)) {
            foreach ($userIds as $userId) {
                $bodyInApps = [
                    'user_id'       => $userId,
                    'token'         => null,
                    'notifications' => $responseNotofocations->data,
                    'send_status'   => 'pending',
                    'is_viewed'     => false,
                    'is_read'       => false,
                    'created_at'    => $coupon->created_at
                ];

                $inApps = $mongoClient->setFormParam($bodyInApps)
                            ->setEndPoint('user-notifications') // express endpoint
                            ->request('POST');
            }
        }
    }
});


/**
 * Listen on:    `orbit.coupon.pushnotoficationupdate.after.commit`
 * Purpose:      Handle push and inapps notification
 * @author firmansyah <firmansyah@dominopos.com>
 *
 * @param CouponAPIController $controller - The instance of the CouponAPIController or its subclass
 * @param News $coupon - Instance of object News
 */
Event::listen('orbit.coupon.pushnotoficationupdate.after.commit', function($controller, $updatedcoupon)
{
    //Check date and status
    $timezone = 'Asia/Jakarta'; // now with jakarta timezone
    $timestamp = date("Y-m-d H:i:s");
    $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
    $dateTimeNow = $date->setTimezone('Asia/Jakarta')->toDateTimeString();

    if ( ($updatedcoupon->status === 'active') && ($dateTimeNow >= $updatedcoupon->begin_date) ) {
        $dateTime = $updatedcoupon->updated_at;

        // Get data user-notification : where object_id object_type
        $queryString['object_id'] = $updatedcoupon->promotion_id;
        $queryString['object_type'] = 'coupon';
        $queryString['status'] = 'pending';

        $mongoConfig = Config::get('database.mongodb');
        $mongoClient = MongoClient::create($mongoConfig);
        $endPoint = "store-object-notifications";
        $storeObjectNotifications = $mongoClient->setQueryString($queryString)
                                ->setEndPoint($endPoint)
                                ->request('GET');

        // Send to onesignal
        $notificationTokens = $storeObjectNotifications->data->records[0]->notification->notification_tokens;
        if (isset($notificationTokens) && count($notificationTokens) > 0) {

            $mongoNotifId = $storeObjectNotifications->data->records[0]->_id;
            $launchUrl = $storeObjectNotifications->data->records[0]->notification->launch_url;
            $headings = $storeObjectNotifications->data->records[0]->notification->headings;
            $contents = $storeObjectNotifications->data->records[0]->notification->contents;
            $imageUrl = $storeObjectNotifications->data->records[0]->notification->attachment_url;

            // add query string for activity recording
            $newUrl =  $launchUrl . '?notif_id=' . $mongoNotifId;

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

            // Update status pending to sent
            $bodyUpdate['_id'] = $mongoNotifId;
            $bodyUpdate['sent_at'] = $dateTime;
            $bodyUpdate['status'] = 'sent';

            $responseUpdate = $mongoClient->setFormParam($bodyUpdate)
                                        ->setEndPoint($endPoint) // express endpoint
                                        ->request('PUT');
        }

        // Send as inApps notification
        $userIds = $storeObjectNotifications->data->records[0]->notification->user_ids;
        if (isset($userIds) && count($userIds) > 0) {
            foreach ($userIds as $userId) {
                $bodyInApps = [
                    'user_id'       => $userId,
                    'token'         => null,
                    'notifications' => $notif->data,
                    'send_status'   => 'sent',
                    'is_viewed'     => false,
                    'is_read'       => false,
                    'created_at'    => $dateTime
                ];

                $inApps = $mongoClient->setFormParam($bodyInApps)
                            ->setEndPoint('user-notifications') // express endpoint
                            ->request('POST');
            }
        }

        $bodyUpdate['sent_at'] = $dateTime;
        $bodyUpdate['_id'] = $mongoNotifId;

        $responseUpdate = $mongoClient->setFormParam($bodyUpdate)
                                    ->setEndPoint('notifications') // express endpoint
                                    ->request('PUT');
    }
});