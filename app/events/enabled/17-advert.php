<?php
/**
 * Event listener for Advert related events.
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:    `orbit.advert.postnewadvert.after.save`
 * Purpose:      Handle file upload on advert creation
 *
 * @param AdvertAPIController $controller - The instance of the AdvertAPIController or its subclass
 * @param Advert $advert - Instance of object Advert
 */
Event::listen('orbit.advert.postnewadvert.after.save', function($controller, $advert)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    // This will be used on UploadAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    $_POST['advert_id'] = $advert->advert_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('advert.new')
                                   ->postUploadAdvertImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['advert_id']);

    $advert->setRelation('media', $response->data);
    $advert->media = $response->data;
    $advert->image = $response->data[0]->path;
});

/**
 * Listen on:       `orbit.advert.postupdateadvert.after.save`
 *   Purpose:       Handle file upload on advert update
 *
 * @param AdvertAPIController $controller - The instance of the AdvertAPIController or its subclass
 * @param Advert $advert - Instance of object Advert
 */
Event::listen('orbit.advert.postupdateadvert.after.save', function($controller, $advert)
{
    $images = OrbitInput::files('images');

    if (! empty($images)) {
        $_POST['advert_id'] = $advert->advert_id;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('advert.update')
                                       ->postUploadAdvertImage();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $advert->load('media');
        $advert->image = $response->data[0]->path;
    }

});