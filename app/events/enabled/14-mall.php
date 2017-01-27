<?php
/**
 * Event listener for Merchant related events.
 *
 * @author Irianto <irianto@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;


/**
 * Listen on:    `orbit.mall.postnewmall.after.save`
 * Purpose:      Handle file upload on mallgroup creation
 *
 * @author Irianto <irianto@dominopos.com>
 *
 * @param EventAPIController $controller - The instance of the EventAPIController or its subclass
 * @param Event $mall - Instance of object Event
 */
Event::listen('orbit.mall.postnewmall.after.save', function($controller, $mall)
{
    //Upload mall logo
    $logo = OrbitInput::files('logo');

    if (! empty($logo)) {
        $_POST['merchant_id'] = $mall->merchant_id;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('mall.update')
                                       ->postUploadMallLogo();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

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
                'object_id'     => $mall->merchant_id,
                'media_name_id' => $response->data['extras']->mediaNameId,
                'old_path'      => $response->data['extras']->oldPath,
                'es_type'       => 'mall',
                'es_id'         => $mall->merchant_id,
                'bucket_name'   => $bucketName
            ], $queueName);
        }
    }
    $mall->load('mediaLogo');

    // Update mall maps
    $maps = OrbitInput::files('maps');
    if (! empty($maps)) {
        $_POST['merchant_id'] = $mall->merchant_id;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('mall.update')
                                       ->postUploadMallMap();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $mall->setRelation('media_map', $response->data);
        $mall->media_map = $response->data;

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
                'object_id'     => $mall->merchant_id,
                'media_name_id' => $response->data['extras']->mediaNameId,
                'old_path'      => $response->data['extras']->oldPath,
                'es_type'       => 'mall',
                'es_id'         => $mall->merchant_id,
                'bucket_name'   => $bucketName
            ], $queueName);
        }
    }
    $mall->load('mediaMap');
});


/**
 * Listen on:     `orbit.mall.postupdatemall.after.save`
 * Purpose:       Handle file upload on mall update
 *
 * @author Irianto <irianto@dominopos.com>
 * @param MallAPIController $controller - The instance of the MallAPIController or its subclass
 * @param Mall $mall - Instance of object Mall
 */
Event::listen('orbit.mall.postupdatemall.after.save', function($controller, $mall)
{
    $logo = OrbitInput::files('logo');
    $_POST['merchant_id'] = $mall->merchant_id;

    // Update logo
    if (empty($logo)) {
        // Delete mall logo
        OrbitInput::post('logo', function($logo_string) use ($controller, $mall) {
            if (empty(trim($logo_string))) {
                // This will be used on UploadAPIController
                App::instance('orbit.upload.user', $controller->api->user);

                $response = UploadAPIController::create('raw')
                                               ->setCalledFrom('mall.update')
                                               ->postDeleteMallLogo();
                if ($response->code !== 0)
                {
                    throw new \Exception($response->message, $response->code);
                }

                // queue for data amazon s3
                $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);
                $bucketName = Config::get('orbit.cdn.providers.S3.bucket_name', '');
                $queueName = Config::get('orbit.cdn.queue_name', 'cdn_upload');

                if ($usingCdn) {
                    $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadDeleteQueue';
                    Queue::push($queueFile, [
                        'object_id'     => $mall->merchant_id,
                        'media_name_id' => 'mall_logo',
                        'old_path'      => $response->data['extras']->oldPath,
                        'es_type'       => 'mall',
                        'es_id'         => $mall->merchant_id,
                        'bucket_name'   => $bucketName
                    ], $queueName);
                }
            }
        });
    } else {
        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('mall.update')
                                       ->postUploadMallLogo();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

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
                'object_id'     => $mall->merchant_id,
                'media_name_id' => $response->data['extras']->mediaNameId,
                'old_path'      => $response->data['extras']->oldPath,
                'es_type'       => 'mall',
                'es_id'         => $mall->merchant_id,
                'bucket_name'   => $bucketName
            ], $queueName);
        }
    }
    $mall->load('mediaLogo');

    // Upload map
    $maps = OrbitInput::files('maps');
    if (! empty($maps)) {
        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('mall.update')
                                       ->postUploadMallMap();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $mall->setRelation('media_map', $response->data);
        $mall->media_map = $response->data;

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
                'object_id'     => $mall->merchant_id,
                'media_name_id' => $response->data['extras']->mediaNameId,
                'old_path'      => $response->data['extras']->oldPath,
                'es_type'       => 'mall',
                'es_id'         => $mall->merchant_id,
                'bucket_name'   => $bucketName
            ], $queueName);
        }
    }
    $mall->load('mediaMap');

});

/**
 * Listen on:    `orbit.mall.postnewmall.after.commit`
 * Purpose:      Post actions after the data has been successfully commited
 *
 * @author Rio Astamal <rio@dominopos.com>
 *
 * @param EventAPIController $controller - The instance of the EventAPIController or its subclass
 * @param Event $mall - Instance of object Event
 */
Event::listen('orbit.mall.postnewmall.after.commit', function($controller, $mall)
{
    // Notify the queueing system to update Elasticsearch document
    Queue::push('Orbit\\Queue\\Elasticsearch\\ESMallCreateQueue', [
        'mall_id' => $mall->merchant_id
    ]);
});

/**
 * Listen on:    `orbit.mall.postupdatemall.after.commit`
 * Purpose:      Post actions after the data has been successfully commited
 *
 * @author Irianto <irianto@dominopos.com>
 *
 * @param EventAPIController $controller - The instance of the EventAPIController or its subclass
 * @param Event $mall - Instance of object Event
 */
Event::listen('orbit.mall.postupdatemall.after.commit', function($controller, $mall)
{
    // Notify the queueing system to update Elasticsearch document
    Queue::push('Orbit\\Queue\\Elasticsearch\\ESMallUpdateQueue', [
        'mall_id' => $mall->merchant_id,
        'updated_related' => TRUE
    ]);
});

/**
 * Listen on:    `orbit.mall.postdeletemall.after.commit`
 * Purpose:      Post actions after the data has been successfully commited
 *
 * @author Irianto <irianto@dominopos.com>
 *
 * @param EventAPIController $controller - The instance of the EventAPIController or its subclass
 * @param Event $mall - Instance of object Event
 */
Event::listen('orbit.mall.postdeletemall.after.commit', function($controller, $mall)
{
    // Notify the queueing system to delete Elasticsearch document
    Queue::push('Orbit\\Queue\\Elasticsearch\\ESMallDeleteQueue', [
        'mall_id' => $mall->merchant_id
    ]);
});