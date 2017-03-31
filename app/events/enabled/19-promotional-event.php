<?php
/**
 * Event listener for Promotional Event.
 *
 * @author Irianto <irianto@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:    `orbit.promotionalevent.postnewpromotionalevent.after.save`
 * Purpose:      Handle file upload on news creation
 *
 * @param NewsAPIController $controller - The instance of the NewsAPIController or its subclass
 * @param News $promotional_event - Instance of object News
 */
Event::listen('orbit.promotionalevent.postnewpromotionalevent.after.save', function($controller, $promotional_event)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    // This will be used on UploadAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    $_POST['news_id'] = $promotional_event->news_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('news.new')
                                   ->postUploadNewsImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['news_id']);

    $promotional_event->setRelation('media', $response->data);
    $promotional_event->media = $response->data;
    $promotional_event->image = $response->data[0]->path;

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
            'object_id'     => $promotional_event->news_id,
            'media_name_id' => $response->data['extras']->mediaNameId,
            'old_path'      => $response->data['extras']->oldPath,
            'es_type'       => $promotional_event->object_type,
            'es_id'         => $promotional_event->news_id,
            'bucket_name'   => $bucketName
        ], $queueName);
    }
});

/**
 * Listen on:       `orbit.promotionalevent.postupdatepromotionalevent.after.save`
 *   Purpose:       Handle file upload on news update
 *
 * @param NewsAPIController $controller - The instance of the NewsAPIController or its subclass
 * @param News $news - Instance of object News
 */
Event::listen('orbit.promotionalevent.postupdatepromotionalevent.after.save', function($controller, $news)
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
 * Listen on:    `orbit.promotionalevent.after.translation.save`
 * Purpose:      Handle file upload on news cause selected language translation
 *
 * @author Irianto <irianto@dominopos.com>
 *
 * @param NewsAPIController $controller
 * @param NewsTranslations $promotional_event_translations
 */
Event::listen('orbit.promotionalevent.after.translation.save', function($controller, $promotional_event_translations)
{

    $image_id = $promotional_event_translations->merchant_language_id;

    $files = OrbitInput::files('image_translation_' . $image_id);
    if (! $files) {
        return;
    }

    $_POST['news_translation_id'] = $promotional_event_translations->news_translation_id;
    $_POST['news_id'] = $promotional_event_translations->news_id;
    $_POST['merchant_language_id'] = $promotional_event_translations->merchant_language_id;
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

    $promotional_event_translations->setRelation('media', $response->data);
    $promotional_event_translations->media = $response->data;
    $promotional_event_translations->image_translation = $response->data[0]->path;

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
            'object_id'     => $promotional_event_translations->news_translation_id,
            'media_name_id' => $response->data['extras']->mediaNameId,
            'old_path'      => $response->data['extras']->oldPath,
            'es_type'       => $promotional_event_translations->object_type,
            'es_id'         => $promotional_event_translations->news_id,
            'bucket_name'   => $bucketName
        ], $queueName);
    }
});


/**
 * Listen on:    `orbit.promotionalevent.after.rewardtranslation.save`
 * Purpose:      Handle file upload on media cause selected language translation
 *
 * @author Irianto <irianto@dominopos.com>
 *
 * @param NewsAPIController $controller
 * @param RewardDetailTranslations $reward_detail_translation
 */
Event::listen('orbit.promotionalevent.after.rewardtranslation.save', function($controller, $reward_detail_translation)
{

    $image_id = $reward_detail_translation->language_id;

    // upload image sign up desktop
    $files = OrbitInput::files('image_sign_up_desktop_translation_' . $image_id);
    if (! $files) {
        return;
    }

    $_POST['reward_detail_translation'] = $reward_detail_translation->reward_detail_translation_id;
    $_POST['reward_id'] = $reward_detail_translation->reward_id;
    $_POST['language_id'] = $reward_detail_translation->language_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('reward.translations')
                                   ->postUploadSignUpDesktopTranslationImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    unset($_POST['reward_detail_translation']);
    unset($_POST['reward_id']);
    unset($_POST['language_id']);

    $reward_detail_translation->setRelation('media', $response->data);
    $reward_detail_translation->media = $response->data;
    $reward_detail_translation->image_sign_up_desktop_translation = $response->data[0]->path;

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
            'object_id'     => $reward_detail_translation->reward_detail_translation_id,
            'media_name_id' => $response->data['extras']->mediaNameId,
            'old_path'      => $response->data['extras']->oldPath,
            'es_type'       => '', // ask
            'es_id'         => $reward_detail_translation->reward_id,
            'bucket_name'   => $bucketName
        ], $queueName);
    }
});

/**
 * Listen on:    `orbit.promotionalevent.postnewpromotionalevent.after.commit`
 * Purpose:      Send email to marketing after create promotional event
 *
 * @author Irianto <irianto@dominopos.com>
 *
 * @param NewsAPIController $controller
 * @param News $promotional_event
 */
Event::listen('orbit.promotionalevent.postnewpromotionalevent.after.commit', function($controller, $promotional_event)
{
    $timestamp = new DateTime($promotional_event->created_at);
    $date = $timestamp->format('d F Y H:i').' (UTC)';

    if ($promotional_event->object_type === 'promotion') {
        $campaignType = 'Promotion';
    } else {
        $campaignType = 'News';
    }

    // Send email process to the queue
    Queue::push('Orbit\\Queue\\CampaignMail', [
        'campaignType'       => $campaignType,
        'campaignName'       => $promotional_event->news_name,
        'pmpUser'            => $controller->api->user->username,
        'eventType'          => 'created',
        'date'               => $date,
        'campaignId'         => $promotional_event->news_id,
        'mode'               => 'create'
    ]);

});


/**
 * Listen on:    `orbit.promotionalevent.postupdatepromotionalevent.after.commit`
 * Purpose:      Send email to marketing after update news or promotion
 *
 * @author kadek <kadek@dominopos.com>
 *
 * @param NewsAPIController $controller
 * @param News $news
 */
Event::listen('orbit.promotionalevent.postupdatepromotionalevent.after.commit', function($controller, $news, $temporaryContentId)
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
