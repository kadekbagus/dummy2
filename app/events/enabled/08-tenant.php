<?php
/**
 * Event listener for Merchant related events.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;


/**
 * Listen on:    `orbit.tenant.postnewtenant.after.save`
 * Purpose:      Handle file upload on tenant creation with object type tenant or store
 *
 * @author Tian <tian@dominopos.com>
 * @author Firmansyah <firmansyah@dominopos.com>
 *
 * @param EventAPIController $controller - The instance of the EventAPIController or its subclass
 * @param Event $tenant - Instance of object Event
 * @param Event $tenant->object_type - value : tenant or service
 */
Event::listen('orbit.tenant.postnewtenant.after.save', function($controller, $tenant)
{
    $logo = OrbitInput::files('logo');

    if (! empty($logo)) {
        $_POST['merchant_id'] = $tenant->merchant_id;
        $_POST['object_type'] = $tenant->object_type;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('tenant.update')
                                       ->postUploadTenantLogo();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }
    }
    $tenant->load('mediaLogo');

    $maps = OrbitInput::files('maps');

    if (! empty($maps)) {
        $_POST['merchant_id'] = $tenant->merchant_id;
        $_POST['object_type'] = $tenant->object_type;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('tenant.update')
                                       ->postUploadTenantMap();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $tenant->setRelation('media_map', $response->data);
        $tenant->media_map = $response->data;
    }
    $tenant->load('mediaMap');

    $pictures = OrbitInput::files('pictures');

    if (! empty($pictures)) {
        $_POST['merchant_id'] = $tenant->merchant_id;
        $_POST['object_type'] = $tenant->object_type;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('tenant.update')
                                       ->postUploadTenantImage();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $tenant->setRelation('media_image', $response->data);
        $tenant->media_image = $response->data;
    }
    $tenant->load('mediaImage');
});


/**
 * Listen on:       `orbit.tenant.postupdatetenant.after.save`
 *   Purpose:       Handle file upload on tenant update
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @param TenantAPIController $controller - The instance of the TenantAPIController or its subclass
 * @param Retailer $tenant - Instance of object Merchant
 * @param Event $tenant->object_type - value : tenant or service
 *
 */
Event::listen('orbit.tenant.postupdatetenant.after.save', function($controller, $tenant)
{
    $logo = OrbitInput::files('logo');

    if (! empty($logo)) {
        $_POST['merchant_id'] = $tenant->merchant_id;
        $_POST['object_type'] = $tenant->object_type;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('tenant.update')
                                       ->postUploadTenantLogo();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }
    }
    $tenant->load('mediaLogo');

    $maps = OrbitInput::files('maps');

    if (! empty($maps)) {
        $_POST['merchant_id'] = $tenant->merchant_id;
        $_POST['object_type'] = $tenant->object_type;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('tenant.update')
                                       ->postUploadTenantMap();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $tenant->setRelation('media_map', $response->data);
        $tenant->media_map = $response->data;
    }
    $tenant->load('mediaMap');

    $pictures = OrbitInput::files('pictures');

    if (! empty($pictures)) {
        $_POST['merchant_id'] = $tenant->merchant_id;
        $_POST['object_type'] = $tenant->object_type;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('tenant.update')
                                       ->postUploadTenantImage();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $tenant->setRelation('media_image', $response->data);
        $tenant->media_image = $response->data;
    }
    $tenant->load('mediaImage');
});

/**
 * Listen on:    `orbit.tenant.postnewtenant.after.commit`
 * Purpose:      Post actions after the data has been successfully commited
 *
 * @author Irianto <irianto@dominopos.com>
 *
 * @param TenantAPIController $controller - The instance of the TenantAPIController or its subclass
 * @param Tenant $tenant - Instance of object Tenant
 */
Event::listen('orbit.tenant.postupdatetenant.after.commit', function($controller, $tenant)
{

    $_tenant = Tenant::select(DB::raw('oms.country'))
                    ->excludeDeleted('merchants')
                    ->join(DB::raw("(
                            select m.merchant_id, m.name, m.country
                            from {$prefix}merchants m
                            where m.status = 'active'
                                and m.object_type = 'mall'
                            ) as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                    ->where('merchants.merchant_id', '=', $tenant->merchant_id)
                    ->first();

    // update the store on elasticsearch
    // Notify the queueing system to update Elasticsearch document
    if (! empty($_tenant)) {
        Queue::push('Orbit\\Queue\\Elasticsearch\\ESStoreUpdateQueue', [
            'name' => $tenant->name,
            'country' => $_tenant->country,
            'update_related' => TRUE
        ]);
    }

});

Event::listen('orbit.tenant.postnewtenant.after.commit', function($controller, $tenant)
{
    $_tenant = Tenant::select(DB::raw('oms.country'))
                    ->excludeDeleted('merchants')
                    ->join(DB::raw("(
                            select m.merchant_id, m.name, m.country
                            from {$prefix}merchants m
                            where m.status = 'active'
                                and m.object_type = 'mall'
                            ) as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                    ->where('merchants.merchant_id', '=', $tenant->merchant_id)
                    ->first();

    // update the store on elasticsearch
    // Notify the queueing system to update Elasticsearch document
    if (! empty($_tenant)) {
        Queue::push('Orbit\\Queue\\Elasticsearch\\ESStoreUpdateQueue', [
            'name' => $tenant->name,
            'country' => $_tenant->country,
            'update_related' => TRUE
        ]);
    }
});
