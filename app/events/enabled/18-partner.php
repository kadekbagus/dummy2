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
            'object_id'     => $partner->partner_id,
            'media_name_id' => $response->data['extras']->mediaNameId,
            'old_path'      => $response->data['extras']->oldPath,
            'es_type'       => null,
            'es_id'         => null,
            'bucket_name'   => $bucketName
        ], $queueName);
    }
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
            'object_id'     => $partner->partner_id,
            'media_name_id' => $response->data['extras']->mediaNameId,
            'old_path'      => $response->data['extras']->oldPath,
            'es_type'       => null,
            'es_id'         => null,
            'bucket_name'   => $bucketName
        ], $queueName);
    }
});

// for update partner logo
Event::listen('orbit.partner.postupdatepartner.after.save', function($controller, $partner)
{
    $files = OrbitInput::files('logo');
    if (! $files) {
        return;
    }

    // This will be used on UploadAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    $_POST['partner_id'] = $partner->partner_id;

    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('partner.update')
                                   ->postUploadPartnerLogo();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['partner_id']);

    $partner->setRelation('media_logo', $response->data);
    $partner->media_logo = $response->data;
    $partner->logo = $response->data[0]->path;

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
            'object_id'     => $partner->partner_id,
            'media_name_id' => $response->data['extras']->mediaNameId,
            'old_path'      => $response->data['extras']->oldPath,
            'es_type'       => null,
            'es_id'         => null,
            'bucket_name'   => $bucketName
        ], $queueName);
    }
});

// for update partner image
Event::listen('orbit.partner.postupdatepartner.after.save2', function($controller, $partner)
{
    $files = OrbitInput::files('image');
    if (! $files) {
        return;
    }

    // This will be used on UploadAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    $_POST['partner_id'] = $partner->partner_id;

    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('partner.update')
                                   ->postUploadPartnerImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['partner_id']);

    $partner->setRelation('media_image', $response->data);
    $partner->media_image = $response->data;
    $partner->image = $response->data[0]->path;

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
            'object_id'     => $partner->partner_id,
            'media_name_id' => $response->data['extras']->mediaNameId,
            'old_path'      => $response->data['extras']->oldPath,
            'es_type'       => null,
            'es_id'         => null,
            'bucket_name'   => $bucketName
        ], $queueName);
    }
});

// For new/update partner banner
Event::listen('orbit.partner.postupdatepartnerbanner.after.save', function($controller, $partner, $partnerBanner, $bannerIndex)
{
    // This will be used on UploadAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    $inputName = "banners_image_{$bannerIndex}";
    $_POST['media_name_id'] = 'partner_banners';
    $_POST['object_id'] = $partnerBanner->partner_banner_id;

    $response = MediaAPIController::create('raw')
        ->setEnableTransaction(false)
        ->setInputName($inputName)
        ->upload();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    unset($_POST['media_name_id']);
    unset($_POST['object_id']);

    $partnerBanner->setRelation('media', $response->data);
    $partnerBanner->media = $response->data[0]->variants;
    $partnerBanner->media_path = $response->data[0]->variants[1]->path;
});
