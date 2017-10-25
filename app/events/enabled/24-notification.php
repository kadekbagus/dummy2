<?php
/**
 * Event listener for notification related events.
 *
 * @author Shelgi <shelgi@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:    `orbit.notification.postnotification.after.save`
 * Purpose:      Handle file upload on notification creation
 *
 * @param NotificationNewAPIController $controller - The instance of the NewsAPIController or its subclass
 * @param Notification $notification - Instance of object notification
 */
Event::listen('orbit.notification.postnotification.after.save', function($controller, $notificationId)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    $_POST['notification_id'] = $notificationId;

    // This will be used on UploadAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('notification.new')
                                   ->postUploadNotificationImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['notification_id']);

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