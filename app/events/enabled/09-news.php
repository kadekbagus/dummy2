<?php
/**
 * Event listener for News related events.
 *
 * @author Tian <tian@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Orbit\Helper\Util\LandingPageUrlGenerator as LandingPageUrlGenerator;
use Carbon\Carbon as Carbon;
use Orbit\Helper\Util\CdnUrlGenerator;
/**
 * Listen on:    `orbit.news.postnewnews.after.save`
 * Purpose:      Handle file upload on news creation
 *
 * @param NewsAPIController $controller - The instance of the NewsAPIController or its subclass
 * @param News $news - Instance of object News
 */
Event::listen('orbit.news.postnewnews.after.save', function($controller, $news)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    // This will be used on UploadAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    $_POST['news_id'] = $news->news_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('news.new')
                                   ->postUploadNewsImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['news_id']);

    $news->setRelation('media', $response->data);
    $news->media = $response->data;
    $news->image = $response->data[0]->path;

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
            'object_id'     => $news->news_id,
            'media_name_id' => $response->data['extras']->mediaNameId,
            'old_path'      => $response->data['extras']->oldPath,
            'es_type'       => $news->object_type,
            'es_id'         => $news->news_id,
            'bucket_name'   => $bucketName
        ], $queueName);
    }
});


/**
 * Listen on:    `orbit.news.pushnotofication.after.save`
 * Purpose:      Handle push and inapps notification
 *
 * @param NewsAPIController $controller - The instance of the NewsAPIController or its subclass
 * @param News $news - Instance of object News
 */
Event::listen('orbit.news.pushnotofication.after.save', function($controller, $news)
{
    // Push Notification and In Apps notofication, Insert to store_object_notification

    // Get distinct user_id who follows the link to tenant
    $tenantIds = null;
    if (count($news['tenants']) > 0) {
        foreach ($news['tenants'] as $key => $tenant) {
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

        if ($userFollows->data->returned_records > 0) {
            $launchUrl = LandingPageUrlGenerator::create($news->object_type, $news->news_id, $news->news_name)->generateUrl();
            $userIds = $userFollows->data->records;

            $type = 'promotion';
            if ($news->object_type === 'news') {
                $type = 'event';
            }

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

            /*
                TODO :
                attachmentUrl
                defaultLanguage
                headings
                contents
            */

            // Insert notofications
            $bodyNotifications = [
                'title'               => $news->news_name,
                'launch_url'          => $launchUrl,
                // 'attachment_url'      => $attachmentUrl,
                // 'default_language'    => $defaultLanguage,
                // 'headings'            => $headings,
                // 'contents'            => $contents,
                'type'                => $type,
                'status'              => 'pending',
                'created_at'          => $news->created_at,
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
                'object_id' => $news->news_id,
                'object_type' => $news->object_type,
                'user_ids' => $userIds,
                'token' => $notificationToken,
                'status' => 'pending',
                'start_date' => $news->begin_date,
                'created_at' => $news->created_at
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
                    'send_status'   => 'sent',
                    'is_viewed'     => false,
                    'is_read'       => false,
                    'created_at'    => $news->created_at
                ];

                $inApps = $mongoClient->setFormParam($bodyInApps)
                            ->setEndPoint('user-notifications') // express endpoint
                            ->request('POST');
            }
        }

    }

});



/**
 * Listen on:       `orbit.news.postupdatenews.after.save`
 *   Purpose:       Handle file upload on news update
 *
 * @param NewsAPIController $controller - The instance of the NewsAPIController or its subclass
 * @param News $news - Instance of object News
 */
Event::listen('orbit.news.postupdatenews.after.save', function($controller, $news)
{
    $images = OrbitInput::files('images');

    if (! empty($images)) {
        $_POST['news_id'] = $news->news_id;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('news.update')
                                       ->postUploadNewsImage();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $news->load('media');
        $news->image = $response->data[0]->path;

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
                'object_id'     => $news->news_id,
                'media_name_id' => $response->data['extras']->mediaNameId,
                'old_path'      => $response->data['extras']->oldPath,
                'es_type'       => $news->object_type,
                'es_id'         => $news->news_id,
                'bucket_name'   => $bucketName
            ], $queueName);
        }
    }
});


/**
 * Listen on:    `orbit.news.after.translation.save`
 * Purpose:      Handle file upload on news cause selected language translation
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 *
 * @param NewsAPIController $controller
 * @param NewsTranslations $news_translations
 */
Event::listen('orbit.news.after.translation.save', function($controller, $news_translations)
{

    $image_id = $news_translations->merchant_language_id;

    $files = OrbitInput::files('image_translation_' . $image_id);
    if (! $files) {
        return;
    }

    $_POST['news_translation_id'] = $news_translations->news_translation_id;
    $_POST['news_id'] = $news_translations->news_id;
    $_POST['merchant_language_id'] = $news_translations->merchant_language_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('news.translations')
                                   ->postUploadNewsTranslationImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    unset($_POST['news_translation_id']);
    unset($_POST['news_id']);
    unset($_POST['merchant_language_id']);

    $news_translations->setRelation('media', $response->data);
    $news_translations->media = $response->data;
    $news_translations->image_translation = $response->data[0]->path;

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
            'object_id'     => $news_translations->news_translation_id,
            'media_name_id' => $response->data['extras']->mediaNameId,
            'old_path'      => $response->data['extras']->oldPath,
            'es_type'       => $news_translations->object_type,
            'es_id'         => $news_translations->news_id,
            'bucket_name'   => $bucketName
        ], $queueName);
    }
});


/**
 * Listen on:    `orbit.news.postnewnews.after.commit`
 * Purpose:      Send email to marketing after create news or promotion
 *
 * @author kadek <kadek@dominopos.com>
 *
 * @param NewsAPIController $controller
 * @param News $news
 */
Event::listen('orbit.news.postnewnews.after.commit', function($controller, $news)
{
    $timestamp = new DateTime($news->created_at);
    $date = $timestamp->format('d F Y H:i').' (UTC)';

    if ($news->object_type === 'promotion') {
        $campaignType = 'Promotion';
    } else {
        $campaignType = 'News';
    }

    // Send email process to the queue
    Queue::push('Orbit\\Queue\\CampaignMail', [
        'campaignType'       => $campaignType,
        'campaignName'       => $news->news_name,
        'pmpUser'            => $controller->api->user->username,
        'eventType'          => 'created',
        'date'               => $date,
        'campaignId'         => $news->news_id,
        'mode'               => 'create'
    ]);

});


/**
 * Listen on:    `orbit.news.postupdatenews.after.commit`
 * Purpose:      Send email to marketing after update news or promotion
 *
 * @author kadek <kadek@dominopos.com>
 *
 * @param NewsAPIController $controller
 * @param News $news
 */
Event::listen('orbit.news.postupdatenews.after.commit', function($controller, $news, $temporaryContentId)
{
    $timestamp = new DateTime($news->updated_at);
    $date = $timestamp->format('d F Y H:i').' (UTC)';

    if ($news->object_type === 'promotion') {
        $campaignType = 'Promotion';
    } else {
        $campaignType = 'News';
    }

    // Send email process to the queue
    Queue::push('Orbit\\Queue\\CampaignMail', [
        'campaignType'       => $campaignType,
        'campaignName'       => $news->news_name,
        'pmpUser'            => $controller->api->user->username,
        'eventType'          => 'updated',
        'date'               => $date,
        'campaignId'         => $news->news_id,
        'temporaryContentId' => $temporaryContentId,
        'mode'               => 'update'
    ]);

    $prefix = DB::getTablePrefix();
    if ($news->object_type === 'promotion') {
        $promotions = News::excludeDeleted('news')
                        ->select(DB::raw("
                            {$prefix}news.news_id,
                            CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                            THEN {$prefix}campaign_status.campaign_status_name
                            ELSE (CASE WHEN {$prefix}news.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                            FROM {$prefix}news_merchant onm
                                                LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                            WHERE onm.news_id = {$prefix}news.news_id)
                           THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status
                        "))
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->where('news.news_id', '=', $news->news_id)
                        ->where('news.object_type', '=', 'promotion')
                        ->first();

        // delete the es document if the promotion stopped or expired
        if ($promotions->campaign_status === 'stopped' || $promotions->campaign_status === 'expired') {
            // Notify the queueing system to delete Elasticsearch document
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESPromotionDeleteQueue', [
                'news_id' => $promotions->news_id
            ]);

            // Notify the queueing system to update Elasticsearch Suggestion document
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESPromotionSuggestionDeleteQueue', [
                'news_id' => $news->news_id
            ]);
        } else {
            // Notify the queueing system to update Elasticsearch document
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESPromotionUpdateQueue', [
                'news_id' => $promotions->news_id
            ]);
        }
    }

    if ($news->object_type === 'news') {
        $news = News::excludeDeleted('news')
                        ->select(DB::raw("
                            {$prefix}news.news_id,
                            CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                            THEN {$prefix}campaign_status.campaign_status_name
                            ELSE (CASE WHEN {$prefix}news.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                            FROM {$prefix}news_merchant onm
                                                LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                            WHERE onm.news_id = {$prefix}news.news_id)
                           THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status
                        "))
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->where('news.news_id', '=', $news->news_id)
                        ->where('news.object_type', '=', 'news')
                        ->first();

        // delete the es document if the promotion stopped or expired
        if ($news->campaign_status === 'stopped' || $news->campaign_status === 'expired') {
            // Notify the queueing system to delete Elasticsearch document
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESNewsDeleteQueue', [
                'news_id' => $news->news_id
            ]);

            // Notify the queueing system to update Elasticsearch Suggestion document
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESNewsSuggestionDeleteQueue', [
                'news_id' => $news->news_id
            ]);
        } else {
            // Notify the queueing system to update Elasticsearch document
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESNewsUpdateQueue', [
                'news_id' => $news->news_id
            ]);
        }
    }

});

Event::listen('orbit.news.postupdatenews-mallnotification.after.save', function($controller, $news)
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
    $headings = [];
    $contents = [];
    $userIds = null;
    $attachmentPath = null;
    $attachmentRealPath = null;
    $cdnUrl = null;
    $cdnBucketName = null;
    $notificationId = null;
    $tokens = null;

    $prefix = DB::getTablePrefix();
    $malls = News::select(DB::raw("CASE WHEN {$prefix}merchants.object_type ='tenant' THEN {$prefix}merchants.parent_id
                                            ELSE {$prefix}merchants.merchant_id
                                    END as mall_id"))
                    ->excludeDeleted('news')
                    ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                    ->where('news.news_id', $news->news_id)
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
        }
        $tokens = array_unique($tokens);

        $_news = News::select('news.*',
                              DB::raw('default_languages.name as default_language_name'),
                              DB::raw('default_languages.language_id as default_language_id')
                             )
                     ->with('translations.media')
                     ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                     ->join('languages as default_languages', DB::raw('default_languages.name'), '=', 'campaign_account.mobile_default_language')
                     ->where('news_id', '=', $news->news_id)
                     ->first();

        $launchUrl = LandingPageUrlGenerator::create($_news->object_type, $_news->news_id, $_news->news_name)->generateUrl();

        foreach ($news->translations as $key => $value)
        {
            if (!empty($value->news_name) && !empty($value->description))
            {
                $headings[$value->name] = $value->news_name;
                $contents[$value->name] = $value->description;
            }
            if ($value->merchant_language_id === $_news->default_language_id)
            {
                if (count($value->media) !==0)
                {
                    foreach ($value->media as $key => $value_media)
                    {
                        if($value_media->media_name_long === 'news_translation_image_orig')
                        {
                            $attachmentPath = $value_media->file_name;
                            $attachmentRealPath = $value_media->path;
                            $cdnUrl = $value_media->cdn_url;
                            $cdnBucketName = $value_media->cdn_bucket_name;
                        }
                    }
                }
            }
        }


        $dataNotification = [
            'title' => $_news->news_name,
            'launch_url' => $launchUrl,
            'attachment_path' => $attachmentPath,
            'attachment_realpath' => $attachmentRealPath,
            'cdn_url' => $cdnUrl,
            'cdn_bucket_name' => $cdnBucketName,
            'default_language' => $_news->default_language_name,
            'headings' => $headings,
            'contents' => $contents,
            'type' => $_news->object_type == 'news' ? 'event' : 'promotion',
            'status' => 'pending',
            'sent_at' => null,
            'notification_tokens' => $tokens,
            'user_ids' => $userIds,
            'vendor_notification_id' => null,
            'vendor_type' => 'onesignal',
            'is_automatic' => true,
            'mime_type' => 'image/jpeg',
            'target_audience_ids' => null,
            'created_at' => $dateTime
        ];

        $dataNotificationCheck = [
            'title' => $_news->news_name,
            'launch_url' => $launchUrl,
            'type' => $_news->object_type,
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
                'user_ids' => $userIds,
                'tokens' => $tokens,
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
                $_notificationIds = $mallObjectNotif->data->records[0]->notification_ids;
                $_userIds = $mallObjectNotif->data->records[0]->user_ids;
                $_tokens = $mallObjectNotif->data->records[0]->tokens;
                $_notificationIds[] = $notificationId;
                foreach ($userIds as $key => $uservalue) {
                    $_userIds[] = $uservalue;
                }
                foreach ($tokens as $key => $tokenvalue) {
                    $_tokens[] = $tokenvalue;
                }
                $updateMallObjectNotification = [
                    '_id' => $mallObjectNotif->data->records[0]->_id,
                    'notification_ids' => array_unique($_notificationIds),
                    'mall_id' => $mallvalue,
                    'user_ids' => array_unique($_userIds),
                    'tokens' => array_unique($_tokens),
                ];

                $mallObjectNotification = $mongoClient->setFormParam($updateMallObjectNotification)
                                                      ->setEndPoint('mall-object-notifications')
                                                      ->request('PUT');
            }
        }
    }

});



/**
 * Listen on:    `orbit.news.pushnotoficationupdate.after.commit`
 * Purpose:      Handle push and inapps notification
 * @author firmansyah <firmansyah@dominopos.com>
 *
 * @param NewsAPIController $controller - The instance of the NewsAPIController or its subclass
 * @param News $news - Instance of object News
 */
Event::listen('orbit.news.pushnotoficationupdate.after.commit', function($controller, $updatednews)
{
    //Check date and status
    $timezone = 'Asia/Jakarta'; // now with jakarta timezone
    $timestamp = date("Y-m-d H:i:s");
    $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
    $dateTimeNow = $date->setTimezone('Asia/Jakarta')->toDateTimeString();

    if ( ($updatednews->status === 'active') && ($dateTimeNow >= $updatednews->begin_date) ) {

        $dateTime = $updatednews->updated_at;

        // Get data user-notification : where object_id object_type
        $queryString['object_id'] = $updatednews->news_id;
        $queryString['object_type'] = $updatednews->object_type;
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
