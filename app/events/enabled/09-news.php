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
use Orbit\Helper\OneSignal\OneSignal;
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
    if ($news->status === 'active')
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
        $userIds = '';
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
            $userIds = array_values(array_unique($follower));
            $tokenSearch = ['user_ids' => json_encode($userIds), 'notification_provider' => 'onesignal'];
            $tokenData = $mongoClient->setQueryString($tokenSearch)
                                     ->setEndPoint('user-notification-tokens')
                                     ->request('GET');

            if ($tokenData->data->total_records > 0) {
                foreach ($tokenData->data->records as $key => $value) {
                    $tokens[] = $value->notification_token;
                }
                $tokens = array_values(array_unique($tokens));
            }

            $_news = News::select('news.*',
                                  DB::raw('default_languages.name as default_language_name'),
                                  DB::raw('default_languages.language_id as default_language_id')
                                 )
                         ->with('translations.media')
                         ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                         ->join('languages as default_languages', DB::raw('default_languages.name'), '=', 'campaign_account.mobile_default_language')
                         ->where('news_id', '=', $news->news_id)
                         ->first();

            $objectTypeLink = $_news->object_type;
            if ($objectTypeLink === 'news' && $_news->is_having_reward === 'Y') {
                $objectTypeLink = 'promotional-event';
            }

            $launchUrl = LandingPageUrlGenerator::create($objectTypeLink, $_news->news_id, $_news->news_name)->generateUrl();

            $headings = new stdClass();
            $contents = new stdClass();
            $attachmentPath = null;
            $attachmentRealPath = null;
            $cdnUrl = null;
            $cdnBucketName = null;
            $mimeType = null;

            foreach ($news->translations as $key => $value)
            {
                if (!empty($value->news_name) && !empty($value->description))
                {
                    $languageName = $value->name;
                    if (! empty($value->news_name)) {
                        $headings->$languageName = $value->news_name;
                        $contents->$languageName = $value->description;
                    }
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
                                $mimeType = $value_media->mime_type;
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
                'title' => $_news->news_name,
                'launch_url' => $launchUrl,
                'type' => $_news->object_type == 'news' ? 'event' : 'promotion',
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
                        'user_ids' => json_encode($userIds),
                        'tokens' => json_encode($tokens),
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
                    $_notificationIds = (array) $mallObjectNotif->data->records[0]->notification_ids;
                    $_userIds = (array) $mallObjectNotif->data->records[0]->user_ids;
                    $_tokens = (array) $mallObjectNotif->data->records[0]->tokens;
                    $_notificationIds[] = $notificationId;
                    if (!empty($userIds)) {
                        foreach ($userIds as $key => $uservalue) {
                            $_userIds[] = $uservalue;
                        }
                        $_userIds = array_values(array_unique($_userIds));
                    }
                    if (!empty($tokens)) {
                        foreach ($tokens as $key => $tokenvalue) {
                            $_tokens[] = $tokenvalue;
                        }
                        $_tokens = array_values(array_unique($_tokens));
                    }
                    $updateMallObjectNotification = [
                        '_id' => $mallObjectNotif->data->records[0]->_id,
                        'notification_ids' => array_values(array_unique($_notificationIds)),
                        'mall_id' => $mallvalue,
                        'user_ids' => json_encode($_userIds),
                        'tokens' => json_encode($_tokens),
                    ];

                    $mallObjectNotification = $mongoClient->setFormParam($updateMallObjectNotification)
                                                          ->setEndPoint('mall-object-notifications')
                                                          ->request('PUT');
                }
            }
        }
    }
});



/**
 * Listen on: `orbit.news.pushnotificationupdate.after.commit`
 * Purpose: Handle push and inapps notification
 * @author firmansyah <firmansyah@dominopos.com>
 *
 * @param NewsAPIController $controller - The instance of the NewsAPIController or its subclass
 * @param News $news - Instance of object News
 */
Event::listen('orbit.news.postupdatenews-storenotificationupdate.after.commit', function($controller, $updatednews)
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

    if ($updatednews->status === 'active') {

        // check existing notification
        $objectType = $updatednews->object_type;
        if ($objectType === 'news') {
            $objectType = 'event';
        }

        $queryStringStoreObject = [
            'object_id' => $updatednews->news_id,
            'object_type' => $objectType,
        ];

        $storeObjectNotifications = $mongoClient->setQueryString($queryStringStoreObject)
                                ->setEndPoint('store-object-notifications')
                                ->request('GET');

        $newsMain = News::join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                         ->join('languages as default_languages', DB::raw('default_languages.name'), '=', 'campaign_account.mobile_default_language')
                         ->where('news_id', '=', $updatednews->news_id);

        $langNews = $newsMain->select(DB::raw('default_languages.name as default_language_name'))->first();
        $defaultLangName = $langNews->default_language_name;

        $_news = $newsMain->select('news.*', DB::raw('default_languages.name as default_language_name'), DB::raw('default_languages.language_id as default_language_id'))
                         ->with('translations.media')
                         ->first();

        $launchUrl = LandingPageUrlGenerator::create($_news->object_type, $_news->news_id, $_news->news_name)->generateUrl();
        $attachmentPath = null;
        $attachmentRealPath = null;
        $cdnUrl = null;
        $cdnBucketName = null;
        $mimeType = null;
        $headings = new stdClass();
        $contents = new stdClass();

        // get heading, content, and image
        foreach ($_news->translations as $translation) {
            $languageName = $translation['name'];
            if (! empty($translation['news_name'])) {
                $headings->$languageName = $translation['news_name'];
                $contents->$languageName = $translation['description'];
            }

            if ($translation['merchant_language_id'] === $_news->default_language_id) {
                if (! empty($translation->media)) {
                    foreach ($translation->media as $media) {
                        if ($media['media_name_long'] === 'news_translation_image_orig') {
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

        // Insert when no data, update when exist
        if (! empty($storeObjectNotifications->data->records)) {
            // Update name, description and image
            if ($storeObjectNotifications->data->records[0]->status === 'pending') {
                $bodyUpdateNotification['title'] = $_news->news_name;
                $bodyUpdateNotification['launch_url'] = $launchUrl;
                $bodyUpdateNotification['attachment_path'] = $attachmentPath;
                $bodyUpdateNotification['attachment_realpath'] = $attachmentRealPath;
                $bodyUpdateNotification['cdn_url'] = $cdnUrl;
                $bodyUpdateNotification['cdn_bucket_name'] = $cdnBucketName;
                $bodyUpdateNotification['default_language'] = $_news->default_language_name;
                $bodyUpdateNotification['headings'] = $headings;
                $bodyUpdateNotification['contents'] = $contents;
                $bodyUpdateNotification['mime_type'] = $mimeType;
                $bodyUpdateNotification['created_at'] = $dateTime;
                $bodyUpdateNotification['_id'] = $storeObjectNotifications->data->records[0]->notification->_id;
                $updateNotification = $mongoClient->setFormParam($bodyUpdateNotification)
                                            ->setEndPoint('notifications')
                                            ->request('PUT');

                if ($updateNotification) {
                    $storeObjectNotificationId = isset($storeObjectNotifications->data->records[0]->_id) ? $storeObjectNotifications->data->records[0]->_id : '';
                    $bodyUpdateStoreObjectNotifation['notification'] = $updateNotification->data;
                    $bodyUpdateStoreObjectNotifation['_id'] = $storeObjectNotificationId;
                    $updatepdateStoreObjectNotifation = $mongoClient->setFormParam($bodyUpdateStoreObjectNotifation)
                                                ->setEndPoint('store-object-notifications')
                                                ->request('PUT');
                }
            }
        } else {
            // Insert
            $newsLinkToTenant = NewsMerchant::where('news_id', $updatednews->news_id)
                                            ->lists('merchant_id');

            $tenantIds = '';
            if (count($newsLinkToTenant) > 0) {
                $tenantIds = json_encode($newsLinkToTenant);
            }

            // get user_ids and tokens
            $queryStringUserFollow = [
                'object_id'   => $tenantIds,
                'object_type' => 'store'
            ];

            $userFollows = $mongoClient->setQueryString($queryStringUserFollow)
                                    ->setEndPoint('user-id-follows')
                                    ->request('GET');

            $userIds = null;
            $notificationToken = array();

            // If there is any followed by user
            if ($userFollows->data->returned_records > 0) {
                $userIds = $userFollows->data->records;
                $queryStringUserNotifToken['user_ids'] = json_encode($userIds);

                $notificationTokens = $mongoClient->setQueryString($queryStringUserNotifToken)
                                    ->setEndPoint('user-notification-tokens')
                                    ->request('GET');

                if ($notificationTokens->data->total_records > 0) {
                    foreach ($notificationTokens->data->records as $key => $val) {
                        $notificationToken[] = $val->notification_token;
                    }
                }

                // save to notifications collection in mongodb
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
                    'object_id' => $_news->news_id,
                    'object_type' => $objectType,
                    'status' => 'pending',
                    'start_date' => $_news->begin_date,
                    'created_at' => $dateTime
                ];

                $storeObjectNotif = $mongoClient->setFormParam($bodyStoreObjectNotifications)
                                                ->setEndPoint('store-object-notifications')
                                                ->request('POST');
            }
        }

    }
});
