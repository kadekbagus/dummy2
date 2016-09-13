<?php
/**
 * Event listener for News related events.
 *
 * @author Tian <tian@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:    `orbit.news.postnewnews.after.save`
 * Purpose:      Handle file upload on news creation
 *
 * @param NewsAPIController $controller - The instance of the NewsAPIController or its subclass
 * @param News $news - Instance of object News
 */
Event::listen('orbit.news.postnewnews.after.save', function($controller, $news)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    // This will be used on UploadAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    $_POST['news_id'] = $news->news_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('news.new')
                                   ->postUploadNewsImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['news_id']);

    $news->setRelation('media', $response->data);
    $news->media = $response->data;
    $news->image = $response->data[0]->path;
});

/**
 * Listen on:       `orbit.news.postupdatenews.after.save`
 *   Purpose:       Handle file upload on news update
 *
 * @param NewsAPIController $controller - The instance of the NewsAPIController or its subclass
 * @param News $news - Instance of object News
 */
Event::listen('orbit.news.postupdatenews.after.save', function($controller, $news)
{
    $images = OrbitInput::files('images');

    if (! empty($images)) {
        $_POST['news_id'] = $news->news_id;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('news.update')
                                       ->postUploadNewsImage();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $news->load('media');
        $news->image = $response->data[0]->path;
    }

});


/**
 * Listen on:    `orbit.news.after.translation.save`
 * Purpose:      Handle file upload on news cause selected language translation
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 *
 * @param NewsAPIController $controller
 * @param NewsTranslations $news_translations
 */
Event::listen('orbit.news.after.translation.save', function($controller, $news_translations)
{

    $image_id = $news_translations->merchant_language_id;

    $files = OrbitInput::files('image_translation_' . $image_id);
    if (! $files) {
        return;
    }

    $_POST['news_translation_id'] = $news_translations->news_translation_id;
    $_POST['news_id'] = $news_translations->news_id;
    $_POST['merchant_language_id'] = $news_translations->merchant_language_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('news.translations')
                                   ->postUploadNewsTranslationImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    unset($_POST['news_translation_id']);
    unset($_POST['news_id']);
    unset($_POST['merchant_language_id']);

    $news_translations->setRelation('media', $response->data);
    $news_translations->media = $response->data;
    $news_translations->image_translation = $response->data[0]->path;
});


/**
 * Listen on:    `orbit.news.postnewnews.after.commit`
 * Purpose:      Send email to marketing after create news or promotion
 *
 * @author kadek <kadek@dominopos.com>
 *
 * @param NewsAPIController $controller
 * @param News $news
 */
Event::listen('orbit.news.postnewnews.after.commit', function($controller, $news)
{

    $timestamp = new DateTime($news->created_at);
    $date = $timestamp->format('d F Y H:i');

    if ($news->object_type === 'promotion') {
        $campaignType = 'Promotion';
    } else {
        $campaignType = 'News';
    }

    $data = array(
        'campaignType'      => $campaignType,
        'campaignName'      => $news->news_name,
        'pmpUser'           => $controller->api->user->username,
        'eventType'         => 'created',
        'date'              => $date
    );

    $mailviews = array(
        'html' => 'emails.campaign-auto-email.campaign-html',
        'text' => 'emails.campaign-auto-email.campaign-text'
    );

    Mail::queue($mailviews, $data, function($message) use ($data)
    {
        $emailconf = Config::get('orbit.campaign_auto_email.sender');
        $from = $emailconf['email'];
        $name = $emailconf['name'];

        $email = Config::get('orbit.campaign_auto_email.email_list');
        $subject = $data['campaignType'].' - '.$data['campaignName'].' has just been created';
        $message->from($from, $name);
        $message->subject($subject);
        $message->to($email);
    });

});


/**
 * Listen on:    `orbit.news.postupdatenews.after.commit`
 * Purpose:      Send email to marketing after update news or promotion
 *
 * @author kadek <kadek@dominopos.com>
 *
 * @param NewsAPIController $controller
 * @param News $news
 */
Event::listen('orbit.news.postupdatenews.after.commit', function($controller, $news, $newsBeforeUpdate)
{
    $afterUpdatedNews = News::excludeDeleted()->where('news_id', $news->news_id)->first();
    $arrDiff = array_diff($afterUpdatedNews->toArray(), $newsBeforeUpdate->toArray());
    $diff = array();
    foreach ($arrDiff as $key => $value) {

        if ($key != 'updated_at') {
            $different = array();
            $different['column'] = $key;
            $different['before'] = $newsBeforeUpdate[$key];
            $different['after'] = $afterUpdatedNews[$key];

            array_push($diff, $different);
        }
    }

    $timestamp = new DateTime($afterUpdatedNews->updated_at);
    $date = $timestamp->format('d F Y H:i').' (UTC)';

    if ($afterUpdatedNews->object_type === 'promotion') {
        $campaignType = 'Promotion';
    } else {
        $campaignType = 'News';
    }

    $data = array(
        'campaignType'      => $campaignType,
        'campaignName'      => $afterUpdatedNews->news_name,
        'pmpUser'           => $controller->api->user->username,
        'eventType'         => 'updated',
        'date'              => $date,
        'updates'           => $diff,
    );

    $mailviews = array(
        'html' => 'emails.campaign-auto-email.campaign-update-html',
        'text' => 'emails.campaign-auto-email.campaign-update-text'
    );

    Mail::queue($mailviews, $data, function($message) use ($data)
    {
        $emailconf = Config::get('orbit.campaign_auto_email.sender');
        $from = $emailconf['email'];
        $name = $emailconf['name'];

        $email = Config::get('orbit.campaign_auto_email.email_list');
        $subject = $data['campaignType'].' - '.$data['campaignName'].' has just been updated';
        $message->from($from, $name);
        $message->subject($subject);
        $message->to($email);
    });

});
