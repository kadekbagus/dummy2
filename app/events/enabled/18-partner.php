<?php
/**
 * Event listener for Partner related events.
 *
 * @author kadek<kadek@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:    `orbit.partner.postnewpartner.after.save`
 * Purpose:      Handle file upload on partner creation
 *
 * @param PartnerAPIController $controller - The instance of the PartnerAPIController or its subclass
 * @param partner $partner - Instance of object partner
 */

// for saving partner logo
Event::listen('orbit.partner.postnewpartner.after.save', function($controller, $partner)
{
    $files = OrbitInput::files('logo');
    if (! $files) {
        return;
    }

    // This will be used on UploadAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    $_POST['partner_id'] = $partner->partner_id;

    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('partner.new')
                                   ->postUploadPartnerLogo();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['partner_id']);

    $partner->setRelation('media_logo', $response->data);
    $partner->media_logo = $response->data;
    $partner->logo = $response->data[0]->path;
});

// for saving partner image
Event::listen('orbit.partner.postnewpartner.after.save2', function($controller, $partner)
{
    $files = OrbitInput::files('image');
    if (! $files) {
        return;
    }

    // This will be used on UploadAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    $_POST['partner_id'] = $partner->partner_id;

    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('partner.new')
                                   ->postUploadPartnerImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['partner_id']);

    $partner->setRelation('media_image', $response->data);
    $partner->media_image = $response->data;
    $partner->image = $response->data[0]->path;
});

/**
 * Listen on:       `orbit.partner.postupdatepartner.after.save`
 *   Purpose:       Handle file upload on partner update
 *
 * @param PartnerAPIController $controller - The instance of the PartnerAPIController or its subclass
 * @param partner $partner - Instance of object partner
 */
Event::listen('orbit.partner.postupdatepartner.after.save', function($controller, $partner)
{
    $images = OrbitInput::files('images');

    if (! empty($images)) {
        $_POST['partner_id'] = $partner->partner_id;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('partner.update')
                                       ->postUploadPartnerImage();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $partner->load('media');
        $partner->image = $response->data[0]->path;
    }

});