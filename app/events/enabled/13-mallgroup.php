<?php
/**
 * Event listener for Merchant related events.
 *
 * @author Irianto <irianto@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;


/**
 * Listen on:    `orbit.mallgroup.postnewmallgroup.after.save`
 * Purpose:      Handle file upload on mallgroup creation
 *
 * @author Irianto <irianto@dominopos.com>
 *
 * @param EventAPIController $controller - The instance of the EventAPIController or its subclass
 * @param Event $mallgroup - Instance of object Event
 */
Event::listen('orbit.mallgroup.postnewmallgroup.after.save', function($controller, $mallgroup)
{
    $logo = OrbitInput::files('logo');

    if (! empty($logo)) {
        $_POST['merchant_id'] = $mallgroup->merchant_id;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('mallgroup.update')
                                       ->postUploadMallGroupLogo();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }
    }
    $mallgroup->load('mediaLogo');
});


/**
 * Listen on:     `orbit.mallgroup.postupdatemallgroup.after.save`
 * Purpose:       Handle file upload on tenant update
 *
 * @author Irianto <irianto@dominopos.com>
 * @param MallGroupAPIController $controller - The instance of the MallGroupAPIController or its subclass
 * @param MallGroup $mallgroup - Instance of object Mall Group
 */
Event::listen('orbit.mallgroup.postupdatemallgroup.after.save', function($controller, $mallgroup)
{
    $logo = OrbitInput::files('logo');
    $_POST['merchant_id'] = $mallgroup->merchant_id;

    if (empty($logo)) {
        OrbitInput::post('logo', function($logo_string) use ($controller) {
            if (empty(trim($logo_string))) {
                // This will be used on UploadAPIController
                App::instance('orbit.upload.user', $controller->api->user);

                $response = UploadAPIController::create('raw')
                                               ->setCalledFrom('mallgroup.update')
                                               ->postDeleteMallGroupLogo();
                if ($response->code !== 0)
                {
                    throw new \Exception($response->message, $response->code);
                }
            }
        });
    } else {
        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('mallgroup.update')
                                       ->postUploadMallGroupLogo();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }
    }
    $mallgroup->load('mediaLogo');
});
