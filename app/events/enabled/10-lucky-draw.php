<?php
/**
 * Event listener for Lucky Draw related events.
 *
 * @author Tian <tian@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:    `orbit.luckydraw.postnewluckydraw.after.save`
 * Purpose:      Handle file upload on lucky draw creation
 *
 * @param LuckyDrawAPIController $controller - The instance of the LuckyDrawAPIController or its subclass
 * @param LuckyDraw $luckydraw - Instance of object Lucky Draw
 */
Event::listen('orbit.luckydraw.postnewluckydraw.after.save', function($controller, $luckydraw)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    // This will be used on UploadAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    $_POST['lucky_draw_id'] = $luckydraw->lucky_draw_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('luckydraw.new')
                                   ->postUploadLuckyDrawImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['lucky_draw_id']);

    $luckydraw->setRelation('media', $response->data);
    $luckydraw->media = $response->data;
    $luckydraw->image = $response->data[0]->path;
});

/**
 * Listen on:       `orbit.luckydraw.postupdateluckydraw.after.save`
 *   Purpose:       Handle file upload on luckydraw update
 *
 * @param LuckyDrawAPIController $controller - The instance of the LuckyDrawAPIController or its subclass
 * @param LuckyDraw $luckydraw - Instance of object Lucky Draw
 */
Event::listen('orbit.luckydraw.postupdateluckydraw.after.save', function($controller, $luckydraw)
{
    $images = OrbitInput::files('images');

    if (! empty($images)) {
        $_POST['lucky_draw_id'] = $luckydraw->lucky_draw_id;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('luckydraw.update')
                                       ->postUploadLuckyDrawImage();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $luckydraw->load('media');
        $luckydraw->image = $response->data[0]->path;
    }

});
