<?php
/**
 * Event listener for Marketplace related events.
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:    `orbit.marketplace.postnewmarketplace.after.save`
 * Purpose:      Handle file upload on marketplace creation
 *
 * @param MarketplaceNewAPIController $controller - The instance of the MarketplaceNewAPIController or its subclass
 * @param Marketplace $marketplace - Instance of object Marketplace
 */
Event::listen('orbit.marketplace.postnewmarketplace.after.save', function($controller, $marketplace)
{
    $images = Input::file(null);
    if (! $images) {
        return;
    }

    // This will be used on MediaAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    // Use MediaAPIController class to upload the image
    $_POST['media_name_id'] = 'marketplace_image';
    $_POST['object_id'] = $marketplace->marketplace_id;

    $response = MediaAPIController::create('raw')
        ->setEnableTransaction(false)
        ->upload();

    unset($_POST['media_name_id']);
    unset($_POST['object_id']);


    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    $marketplace->setRelation('media', $response->data);
    $marketplace->media = $response->data;
    $marketplace->imagePath = $response->data[0]->variants[0]->path;
});

/**
 * Listen on:       `orbit.marketplace.postupdatemarketplace.after.save`
 *   Purpose:       Handle file upload on marketplace update
 *
 * @param MarketplaceNewAPIController $controller - The instance of the MarketplaceNewAPIController or its subclass
 * @param Marketplace $marketplace - Instance of object Marketplace
 */
Event::listen('orbit.marketplace.postupdatemarketplace.after.save', function($controller, $marketplace)
{
    $images = Input::file(null);

    if (! empty($images)) {
        // This will be used on MediaAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        // Delete previous cover image
        $oldImage = Media::where('object_id', $marketplace->marketplace_id)
            ->where('object_name', 'marketplace')
            ->where('media_name_id', 'marketplace_image')
            ->first();

        if (is_object($oldImage)) {
            $_POST['media_id'] = $oldImage->media_id;
            $deleteResponse = MediaAPIController::create('raw')
                ->setEnableTransaction(false)
                ->delete();
            unset($_POST['media_id']);
        }

        // Use MediaAPIController class to upload the new image
        $_POST['media_name_id'] = 'marketplace_image';
        $_POST['object_id'] = $marketplace->marketplace_id;

        $response = MediaAPIController::create('raw')
            ->setEnableTransaction(false)
            ->upload();

        unset($_POST['media_name_id']);
        unset($_POST['object_id']);

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $marketplace->load('media');
        $marketplace->image = $response->data[0]->variants[0]->path;
    }
});
