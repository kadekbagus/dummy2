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
    }
    $mall->load('mediaLogo');
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
    }
    $mall->load('mediaLogo');
});
