<?php
use OrbitShop\API\v1\Helper\Input as OrbitInput;

Event::listen('orbit.sponsorprovider.postnewsponsorprovider.after.save', function($controller, $news)
{
    $files = OrbitInput::files('logo');
    if (! $files) {
        return;
    }

    // This will be used on UploadAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    $_POST['news_id'] = $news->news_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('sponsorprovider.new')
                                   ->postUploadSponsorBankLogo();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['news_id']);

    $news->setRelation('media', $response->data);
    $news->media = $response->data;
    $news->image = $response->data[0]->path;

    // // queue for data amazon s3
    // $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);

    // if ($usingCdn) {
    //     $bucketName = Config::get('orbit.cdn.providers.S3.bucket_name', '');
    //     $queueName = Config::get('orbit.cdn.queue_name', 'cdn_upload');

    //     $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadNewQueue';
    //     if ($response->data['extras']->isUpdate) {
    //         $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadUpdateQueue';
    //     }

    //     Queue::push($queueFile, [
    //         'object_id'     => $news->news_id,
    //         'media_name_id' => $response->data['extras']->mediaNameId,
    //         'old_path'      => $response->data['extras']->oldPath,
    //         'es_type'       => $news->object_type,
    //         'es_id'         => $news->news_id,
    //         'bucket_name'   => $bucketName
    //     ], $queueName);
    // }
});