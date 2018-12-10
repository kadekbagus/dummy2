<?php
/**
 * Event listener for Articles related events.
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Orbit\Helper\Util\LandingPageUrlGenerator as LandingPageUrlGenerator;
use Carbon\Carbon as Carbon;
use Orbit\Helper\Util\CdnUrlGenerator;
/**
 * Listen on:    `orbit.article.postnewarticle.after.save`
 * Purpose:      Handle file upload on article creation
 *
 * @param ArticleNewAPIController $controller - The instance of the ArticleNewAPIController or its subclass
 * @param Article $article - Instance of object Article
 */
Event::listen('orbit.article.postnewarticle.after.save', function($controller, $article)
{
    $images = Input::file(null);
    if (! $images) {
        return;
    }

    // This will be used on MediaAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    // Use MediaAPIController class to upload the image
    $_POST['media_name_id'] = 'article_cover_image';
    $_POST['object_id'] = $article->article_id;

    $response = MediaAPIController::create('raw')
        ->setEnableTransaction(false)
        ->upload();

    unset($_POST['media_name_id']);
    unset($_POST['object_id']);


    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    $article->setRelation('mediaCover', $response->data);
    $article->mediaCover = $response->data;
    $article->coverImagePath = $response->data[0]->variants[0]->path;
});

/**
 * Listen on:       `orbit.article.postupdatearticle.after.save`
 *   Purpose:       Handle file upload on article update
 *
 * @param ArticleNewAPIController $controller - The instance of the ArticleNewAPIController or its subclass
 * @param Article $article - Instance of object Article
 */
Event::listen('orbit.article.postupdatearticle.after.save', function($controller, $article)
{
    $images = Input::file(null);

    if (! empty($images)) {
        // This will be used on MediaAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        // Delete previous cover image
        $oldCover = Media::where('object_id', $article->article_id)
            ->where('object_name', 'article')
            ->where('media_name_id', 'article_cover_image')
            ->first();

        if (is_object($oldCover)) {
            $_POST['media_id'] = $oldCover->media_id;
            $deleteResponse = MediaAPIController::create('raw')
                ->setEnableTransaction(false)
                ->delete();
            unset($_POST['media_id']);
        }

        // Use MediaAPIController class to upload the new image
        $_POST['media_name_id'] = 'article_cover_image';
        $_POST['object_id'] = $article->article_id;

        $response = MediaAPIController::create('raw')
            ->setEnableTransaction(false)
            ->upload();

        unset($_POST['media_name_id']);
        unset($_POST['object_id']);

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $article->load('mediaCover');
        $article->image = $response->data[0]->variants[0]->path;
    }
});


/**
 * Listen on:    `orbit.article.postnewarticle.after.commit`
 *
 * @author firmansyah <firmansyah@dominopos.com>
 *
 * @param ArticleNewAPIController $controller
 * @param Article $article
 */
Event::listen('orbit.article.postnewarticle.after.commit', function($controller, $article)
{
    // Notify the queueing system to update Elasticsearch document
    Queue::push('Orbit\\Queue\\Elasticsearch\\ESArticleUpdateQueue', [
        'article_id' => $article->article_id
    ]);
});


/**
 * Listen on:    `orbit.article.postupdatearticle.after.commit`
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 *
 * @param ArticleNewAPIController $controller
 * @param Article $article
 */
Event::listen('orbit.article.postupdatearticle.after.commit', function($controller, $article)
{
    // Notify the queueing system to update Elasticsearch document
    Queue::push('Orbit\\Queue\\Elasticsearch\\ESArticleUpdateQueue', [
        'article_id' => $article->article_id
    ]);
});