<?php
/**
 * Event listener for Merchant related events.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Controller\API\v1\Merchant\Merchant\MerchantUploadLogoAPIController;


/**
 * Listen on:    `orbit.tenant.postnewtenant.after.save`
 * Purpose:      Handle file upload on tenant creation with object type tenant or store
 *
 * @author Tian <tian@dominopos.com>
 * @author Firmansyah <firmansyah@dominopos.com>
 *
 * @param EventAPIController $controller - The instance of the EventAPIController or its subclass
 * @param Event $tenant - Instance of object Event
 * @param Event $tenant->object_type - value : tenant or service
 */
Event::listen('orbit.basemerchant.postnewbasemerchant.after.save', function($controller, $baseMerchant)
{
    $logo = OrbitInput::files('logo');

    if (! empty($logo)) {
        $_POST['merchant_id'] = $baseMerchant->base_merchant_id;
        $_POST['object_type'] = 'base_merchant';

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = MerchantUploadLogoAPIController::create('raw')
                                       ->setCalledFrom('merchant.new')
                                       ->postUploadMerchantLogo();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }
    }
    $baseMerchant->load('mediaLogo');

});

/**
 * Listen on: `orbit.tenant.postupdatetenant.after.save`
 * Purpose: Handle file upload on tenant update
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 * @param TenantAPIController $controller - The instance of the TenantAPIController or its subclass
 * @param Retailer $tenant - Instance of object Merchant
 * @param Event $tenant->object_type - value : tenant or service
 *
 */
Event::listen('orbit.basemerchant.postupdatebasemerchant.after.save', function($controller, $baseMerchant)
{
    $logo = OrbitInput::files('logo');

    if (! empty($logo)) {
        $_POST['merchant_id'] = $baseMerchant->base_merchant_id;
        $_POST['object_type'] = $baseMerchant->object_type;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = MerchantUploadLogoAPIController::create('raw')
                                       ->setCalledFrom('merchant.update')
                                       ->postUploadMerchantLogo();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }
    }
    $baseMerchant->load('mediaLogo');
});