<?php
/**
 * Event listener for Base Store related events.
 *
 * @author Irianto <irianto@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Controller\API\v1\Merchant\Store\StoreUploadAPIController;

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

});

Event::listen('orbit.basestore.sync.begin', function($syncObject) {
    // Send email process to the queue
    Queue::push('Orbit\\Queue\\SyncStore\\PreSyncStoreMail', ['sync_id' => $syncObject->sync_id], 'store_sync');
});

Event::listen('orbit.basestore.sync.complete', function($syncObject) {
    // Send email process to the queue
    Queue::push('Orbit\\Queue\\SyncStore\\PostSyncStoreMail', ['sync_id' => $syncObject->sync_id], 'store_sync');
});
