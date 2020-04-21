<?php
/**
 * Event listener for Product related events.
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:    `orbit.newproduct.postnewproduct.after.save`
 * Purpose:      Handle file upload on product creation
 *
 * @param ProductNewAPIController $controller - The instance of the ProductNewAPIController or its subclass
 * @param Product $product - Instance of object Product
 */
Event::listen('orbit.newproduct.postnewproduct.after.save', function($controller, $product)
{
    $media = [];
    $maxPhotos = 4;
    $images = Input::file(null);
    if (! $images) {
        return;
    }

    // This will be used on MediaAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    // Use MediaAPIController class to upload the image
    $_POST['media_name_id'] = 'product_image';
    $_POST['object_id'] = $product->product_id;

    $response = MediaAPIController::create('raw')
                    ->setEnableTransaction(false)
                    ->upload();

    unset($_POST['media_name_id']);

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    // Process product photos...
    $_POST['media_name_id'] = 'product_photos';

    for($i = 0; $i < $maxPhotos; $i++) {

        $inputName = "photo{$i}";
        if (Request::hasFile($inputName)) {

            $response = MediaAPIController::create('raw')
                                        ->setInputName($inputName)
                                        ->setEnableTransaction(false)
                                        ->upload();

            if ($response->code !== 0)
            {
                throw new \Exception($response->message, $response->code);
            }

            $media[] = $response->data;
        }
    }

    $product->setRelation('media', $response->data);
    $product->media = $response->data;
    $product->imagePath = $response->data[0]->variants[0]->path;
});

/**
 * Listen on:       `orbit.newproduct.postupdateproduct.after.save`
 *   Purpose:       Handle file upload on product update
 *
 * @param ProductNewAPIController $controller - The instance of the ProductNewAPIController or its subclass
 * @param Product $product - Instance of object Product
 */
Event::listen('orbit.newproduct.postupdateproduct.after.save', function($controller, $product)
{
    $images = Input::file(null);

    if (! empty($images)) {
        // This will be used on MediaAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        // Delete previous cover image
        $oldImage = Media::where('object_id', $product->product_id)
            ->where('object_name', 'product')
            ->where('media_name_id', 'product_image')
            ->first();

        if (is_object($oldImage)) {
            $_POST['media_id'] = $oldImage->media_id;
            $deleteResponse = MediaAPIController::create('raw')
                ->setEnableTransaction(false)
                ->delete();
            unset($_POST['media_id']);
        }

        // Use MediaAPIController class to upload the new image
        $_POST['media_name_id'] = 'product_image';
        $_POST['object_id'] = $product->product_id;

        $response = MediaAPIController::create('raw')
            ->setEnableTransaction(false)
            ->upload();

        unset($_POST['media_name_id']);
        unset($_POST['object_id']);

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $product->load('media');
        $product->image = $response->data[0]->variants[0]->path;
    }


});


// New GAME
Event::listen('orbit.newgame.postnewgame.after.save', function($controller, $game)
{
    $images = Input::file(null);
    if (! $images) {
        return;
    }

    // This will be used on MediaAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    // Use MediaAPIController class to upload the image
    $_POST['media_name_id'] = 'game_image';
    $_POST['object_id'] = $game->game_id;

    $response = MediaAPIController::create('raw')
        ->setEnableTransaction(false)
        ->upload();

    unset($_POST['media_name_id']);
    unset($_POST['object_id']);


    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    $game->setRelation('media', $response->data);
    $game->media = $response->data;
    $game->imagePath = $response->data[0]->variants[0]->path;
});

// UPDATE GAME
Event::listen('orbit.updategame.postupdategame.after.save', function($controller, $game)
{
    $images = Input::file(null);

    if (! empty($images)) {
        // This will be used on MediaAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        // Delete previous cover image
        $oldImage = Media::where('object_id', $game->game_id)
            ->where('object_name', 'game')
            ->where('media_name_id', 'game_image')
            ->first();

        if (is_object($oldImage)) {
            $_POST['media_id'] = $oldImage->media_id;
            $deleteResponse = MediaAPIController::create('raw')
                ->setEnableTransaction(false)
                ->delete();
            unset($_POST['media_id']);
        }

        // Use MediaAPIController class to upload the new image
        $_POST['media_name_id'] = 'game_image';
        $_POST['object_id'] = $game->game_id;

        $response = MediaAPIController::create('raw')
            ->setEnableTransaction(false)
            ->upload();

        unset($_POST['media_name_id']);
        unset($_POST['object_id']);

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $game->load('media');
        $game->image = $response->data[0]->variants[0]->path;
    }
});
