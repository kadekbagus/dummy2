<?php
/**
 * Event listener for Merchant related events.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:       `orbit.merchant.postnewmerchant.after.save`
 *   Purpose:       Handle file upload on merchant creation
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @param MerchantAPIController $controller - The instance of the MerchantAPIController or its subclass
 * @param Merchant $merchant - Instance of object Merchant
 */
Event::listen('orbit.merchant.postnewmerchant.after.save', function($controller, $merchant)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    $_POST['merchant_id'] = $merchant->merchant_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('merchant.new')
                                   ->postUploadMerchantLogo();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['merchant_id']);

    $merchant->setRelation('media', $response->data);
    $merchant->media = $response->data;
    $merchant->logo = $response->data[0]->path;
});

/**
 * Listen on:       `orbit.merchant.postupdatemerchant.after.save`
 *   Purpose:       Handle file upload on merchant update
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @param MerchantAPIController $controller - The instance of the MerchantAPIController or its subclass
 * @param Merchant $merchant - Instance of object Merchant
 */
Event::listen('orbit.merchant.postupdatemerchant.after.save', function($controller, $merchant)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('merchant.update')
                                   ->postUploadMerchantLogo();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    $merchant->setRelation('media', $response->data);
    $merchant->media = $response->data;
    $merchant->logo = $response->data[0]->path;
});
