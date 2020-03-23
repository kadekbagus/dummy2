<?php
/**
 * Event listener for TelcoOperator related events.
 *
 * @author ahmad<ahmad@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:    `orbit.telco.postnewtelco.after.save`
 * Purpose:      Handle file upload on telco creation
 *
 * @param TelcoOperatorAPIController $controller - The instance of the TelcoOperatorAPIController or its subclass
 * @param telco $telco - Instance of object telco
 */

// for saving telco logo
Event::listen('orbit.telco.postnewtelco.after.save', function($controller, $telco)
{
    $files = OrbitInput::files('logo');
    if (! $files) {
        return;
    }

    // This will be used on UploadAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    $_POST['telco_operator_id'] = $telco->telco_operator_id;

    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('telco.new')
                                   ->postUploadTelcoLogo();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['telco_operator_id']);

    $telco->setRelation('media_logo', $response->data);
    $telco->media_logo = $response->data;
    $telco->logo = $response->data[0]->path;

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
            'object_id'     => $telco->telco_operator_id,
            'media_name_id' => $response->data['extras']->mediaNameId,
            'old_path'      => $response->data['extras']->oldPath,
            'es_type'       => null,
            'es_id'         => null,
            'bucket_name'   => $bucketName
        ], $queueName);
    }
});

// for update telco logo
Event::listen('orbit.telco.postupdatetelco.after.save', function($controller, $telco)
{
    $files = OrbitInput::files('logo');
    if (! $files) {
        return;
    }

    // This will be used on UploadAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    $_POST['telco_operator_id'] = $telco->telco_operator_id;

    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('telco.update')
                                   ->postUploadTelcoLogo();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['telco_operator_id']);

    $telco->setRelation('media_logo', $response->data);
    $telco->media_logo = $response->data;
    $telco->logo = $response->data[0]->path;

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
            'object_id'     => $telco->telco_operator_id,
            'media_name_id' => $response->data['extras']->mediaNameId,
            'old_path'      => $response->data['extras']->oldPath,
            'es_type'       => null,
            'es_id'         => null,
            'bucket_name'   => $bucketName
        ], $queueName);
    }
});
