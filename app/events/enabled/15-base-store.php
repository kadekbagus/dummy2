<?php
/**
 * Event listener for Base Store related events.
 *
 * @author Irianto <irianto@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Controller\API\v1\Merchant\Store\StoreUploadAPIController;
use Orbit\Controller\API\v1\Merchant\Store\StoreUploadBannerAPIController;

/**
 * Listen on:    `orbit.basestore.postnewstore.after.save`
 * Purpose:      Handle file upload on base store creation
 *
 * @author Irianto <irianto@dominopos.com>
 *
 * @param StoreNewAPIController $controller - The instance of the StoreNewAPIController or its subclass
 * @param BaseStore $base_store - Instance of object Event
 */
Event::listen('orbit.basestore.postnewstore.after.save', function($controller, $base_store)
{
    // base store images
    $images = OrbitInput::files('pictures');

    if (! empty($images)) {
        $_POST['base_store_id'] = $base_store->base_store_id;

        // This will be used on StoreUploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = StoreUploadAPIController::create('raw')
                       ->setCalledFrom('basestore.update')
                       ->postUploadBaseStoreImage();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $base_store->setRelation('media_image', $response->data);
        $base_store->media_image = $response->data;
    }
    $base_store->load('mediaImage');
    $base_store->load('mediaImageOrig');

    // base store map
    $map = OrbitInput::files('maps');

    if (! empty($map)) {
        $_POST['base_store_id'] = $base_store->base_store_id;

        // This will be used on StoreUploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = StoreUploadAPIController::create('raw')
                       ->setCalledFrom('basestore.update')
                       ->postUploadBaseStoreMap();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $base_store->setRelation('media_map', $response->data);
        $base_store->media_map = $response->data;
    }
    $base_store->load('mediaMap');
    $base_store->load('mediaMapOrig');

    // 3rd party picture (grab)
    $grab_pictures = OrbitInput::files('grab_pictures');

    if (! empty($grab_pictures)) {
        $_POST['base_store_id'] = $base_store->base_store_id;

        // This will be used on StoreUploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = StoreUploadAPIController::create('raw')
                       ->setCalledFrom('basestore.new')
                       ->postUploadBaseStoreImageGrab();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $base_store->setRelation('media_image_grab', $response->data);
        $base_store->media_image_grab = $response->data;
    }
    $base_store->load('mediaImageGrab');
    $base_store->load('mediaImageGrabOrig');

    // store banner
    $banner = OrbitInput::files('banner');

    if (! empty($banner)) {
        $_POST['base_store_id'] = $base_store->base_store_id;
        // This will be used on StoreUploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = StoreUploadBannerAPIController::create('raw')
                       ->setCalledFrom('store.new')
                       ->postUploadStoreBanner();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $base_store->setRelation('media_banner', $response->data);
        $base_store->media_banner = $response->data;
    }
    $base_store->load('mediaBanner');
});


/**
 * Listen on:       `orbit.basestore.postupdatestore.after.save`
 *   Purpose:       Handle file upload on base store update
 *
 * @author Irianto <irianto@dominopos.com>
 * @param StoreUpdateAPIController $controller - The instance of the StoreUpdateAPIController or its subclass
 * @param BaseStore $base_store - Instance of object base store
 */
Event::listen('orbit.basestore.postupdatestore.after.save', function($controller, $base_store)
{
    // base store images
    $images = OrbitInput::files('pictures');

    if (! empty($images)) {
        $_POST['base_store_id'] = $base_store->base_store_id;

        // This will be used on StoreUploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = StoreUploadAPIController::create('raw')
                                       ->setCalledFrom('basestore.update')
                                       ->postUploadBaseStoreImage();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $base_store->setRelation('media_image', $response->data);
        $base_store->media_image = $response->data;
    }
    $base_store->load('mediaImage');
    $base_store->load('mediaImageOrig');

    // base store map
    $map = OrbitInput::files('maps');

    if (! empty($map)) {
        $_POST['base_store_id'] = $base_store->base_store_id;

        // This will be used on StoreUploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = StoreUploadAPIController::create('raw')
                                       ->setCalledFrom('basestore.update')
                                       ->postUploadBaseStoreMap();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $base_store->setRelation('media_map', $response->data);
        $base_store->media_map = $response->data;
    }
    $base_store->load('mediaMap');
    $base_store->load('mediaMapOrig');

    // 3rd party picture (grab)
    $grab_pictures = OrbitInput::files('grab_pictures');

    if (! empty($grab_pictures)) {
        $_POST['base_store_id'] = $base_store->base_store_id;

        // This will be used on StoreUploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = StoreUploadAPIController::create('raw')
                       ->setCalledFrom('basestore.update')
                       ->postUploadBaseStoreImageGrab();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $base_store->setRelation('media_image_grab', $response->data);
        $base_store->media_image_grab = $response->data;
    }
    $base_store->load('mediaImageGrab');
    $base_store->load('mediaImageGrabOrig');

    // update required 3party grab field
    Queue::push('Orbit\\Queue\\GTMRequirementFieldUpdateQueue', ['id' => $base_store->base_store_id, 'from' => 'base_store']);

    // store banner
    $banner = OrbitInput::files('banner');

    if (! empty($banner)) {
        $_POST['base_store_id'] = $base_store->base_store_id;

        // This will be used on StoreUploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = StoreUploadBannerAPIController::create('raw')
                       ->setCalledFrom('store.update')
                       ->postUploadStoreBanner();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $base_store->setRelation('media_banner', $response->data);
        $base_store->media_banner = $response->data;
    }
    $base_store->load('mediaBanner');
});

Event::listen('orbit.basestore.sync.begin', function($syncObject) {
    $queueName = Config::get('queue.connections.store_sync.queue', 'store_sync');
    // Send email process to the queue
    Queue::push('Orbit\\Queue\\SyncStore\\PreSyncStoreMail', ['sync_id' => $syncObject->sync_id], $queueName);
});

Event::listen('orbit.basestore.sync.complete', function($syncObject) {
    $queueName = Config::get('queue.connections.store_sync.queue', 'store_sync');
    // Send email process to the queue
    Queue::push('Orbit\\Queue\\SyncStore\\PostSyncStoreMail', ['sync_id' => $syncObject->sync_id], $queueName);
});
