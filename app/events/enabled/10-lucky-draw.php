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

/**
 * Listen on:    `orbit.luckydraw.after.translation.save`
 * Purpose:      Handle file upload on lucky draw with language translation
 *
 * @author Ahmad Anshori <ahmad@dominopos.com>
 *
 * @param LuckyDrawAPIController $controller
 * @param LuckyDrawTranslations $lucky_draw_translations
 */
Event::listen('orbit.luckydraw.after.translation.save', function($controller, $lucky_draw_translations)
{
    $image_id = $lucky_draw_translations->merchant_language_id;

    $files = OrbitInput::files('image_translation_' . $image_id);
    if (! $files) {
        return;
    }

    App::instance('orbit.upload.user', $controller->api->user);

    $_POST['lucky_draw_translation_id'] = $lucky_draw_translations->lucky_draw_translation_id;
    $_POST['lucky_draw_id'] = $lucky_draw_translations->lucky_draw_id;
    $_POST['merchant_language_id'] = $lucky_draw_translations->merchant_language_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('luckydraw.translations')
                                   ->postUploadLuckyDrawTranslationImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    unset($_POST['lucky_draw_translation_id']);
    unset($_POST['lucky_draw_id']);
    unset($_POST['merchant_language_id']);

    $lucky_draw_translations->setRelation('media', $response->data);
    $lucky_draw_translations->media = $response->data;
    $lucky_draw_translations->image_translation = $response->data[0]->path;
});

/**
 * Listen on:    `orbit.luckydraw.after.announcement.save`
 * Purpose:      Handle file upload on lucky draw announcement
 *
 * @author Ahmad Anshori <ahmad@dominopos.com>
 *
 * @param LuckyDrawAPIController $controller
 * @param LuckyDrawAnnouncement $lucky_draw_announcement
 */
Event::listen('orbit.luckydraw.after.announcement.save', function($controller, $lucky_draw_announcement)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    $_POST['lucky_draw_announcement_id'] = $luckydraw->lucky_draw_announcement_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('luckydrawannouncement.new')
                                   ->postUploadLuckyDrawAnnouncementImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['lucky_draw_announcement_id']);

    $luckydraw->setRelation('media', $response->data);
    $luckydraw->media = $response->data;
    $luckydraw->image = $response->data[0]->path;
});

/**
 * Listen on:    `orbit.luckydraw.after.announcement.translation.save`
 * Purpose:      Handle file upload on lucky draw announcement translations
 *
 * @author Ahmad Anshori <ahmad@dominopos.com>
 *
 * @param LuckyDrawAPIController $controller
 * @param LuckyDrawAnnouncement $lucky_draw_announcement_translations
 */
Event::listen('orbit.luckydraw.after.announcement.translation.save', function($controller, $lucky_draw_announcement_translations)
{
    $image_id = $lucky_draw_announcement_translations->merchant_language_id;

    $files = OrbitInput::files('image_translation_' . $image_id);
    if (! $files) {
        return;
    }

    $_POST['lucky_draw_announcement_translation_id'] = $lucky_draw_announcement_translations->lucky_draw_announcement_translation_id;
    $_POST['lucky_draw_announcement_id'] = $lucky_draw_announcement_translations->lucky_draw_announcement_id;
    $_POST['merchant_language_id'] = $lucky_draw_announcement_translations->merchant_language_id;

    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('luckydrawannouncement.translations')
                                   ->postUploadLuckyDrawAnnouncementTranslationImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    unset($_POST['lucky_draw_announcement_translation_id']);
    unset($_POST['lucky_draw_announcement_id']);
    unset($_POST['merchant_language_id']);

    $lucky_draw_announcement_translations->setRelation('media', $response->data);
    $lucky_draw_announcement_translations->media = $response->data;
    $lucky_draw_announcement_translations->image_translation = $response->data[0]->path;
});
