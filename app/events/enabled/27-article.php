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
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    // This will be used on UploadAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    $_POST['article_id'] = $article->article_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('article.new')
                                   ->postUploadNewsImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['article_id']);

    $article->setRelation('media', $response->data);
    $article->media = $response->data;
    $article->image = $response->data[0]->path;

    // queue for data amazon s3
    $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);

    if ($usingCdn) {
        $bucketName = Config::get('orbit.cdn.providers.S3.bucket_name', '');
        $queueName = Config::get('orbit.cdn.queue_name', 'cdn_upload');

        $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadNewQueue';
        if ($response->data['extras']->isUpdate) {
            $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadUpdateQueue';
        }

        Queue::push($queueFile, [
            'object_id'     => $article->article_id,
            'media_name_id' => $response->data['extras']->mediaNameId,
            'old_path'      => $response->data['extras']->oldPath,
            'es_type'       => $article->object_type,
            'es_id'         => $article->article_id,
            'bucket_name'   => $bucketName
        ], $queueName);
    }
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
    $images = OrbitInput::files('images');

    if (! empty($images)) {
        $_POST['article_id'] = $article->article_id;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('article.update')
                                       ->postUploadNewsImage();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $article->load('media');
        $article->image = $response->data[0]->path;

        // queue for data amazon s3
        $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);

        if ($usingCdn) {
            $bucketName = Config::get('orbit.cdn.providers.S3.bucket_name', '');
            $queueName = Config::get('orbit.cdn.queue_name', 'cdn_upload');

            $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadNewQueue';
            if ($response->data['extras']->isUpdate) {
                $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadUpdateQueue';
            }

            Queue::push($queueFile, [
                'object_id'     => $article->article_id,
                'media_name_id' => $response->data['extras']->mediaNameId,
                'old_path'      => $response->data['extras']->oldPath,
                'es_type'       => $article->object_type,
                'es_id'         => $article->article_id,
                'bucket_name'   => $bucketName
            ], $queueName);
        }
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