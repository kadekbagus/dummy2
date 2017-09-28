<?php
/**
 * Event listener for Wallet Operator related events.
 *
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:    `orbit.walletoperator.postnewwalletoperator.after.save`
 * Purpose:      Handle file upload on wallet operator creation
 *
 * @author kadek <kadek@dominopos.com>
 *
 * @param WalletOperatorAPIController $controller - The instance of the WalletOperatorAPIController or its subclass
 * @param WalletOperator $walletOperator - Instance of object Wallet Operator
 */
Event::listen('orbit.walletoperator.postnewwalletoperator.after.save', function($controller, $walletOperator)
{
    $files = OrbitInput::files('logo');
    if (! $files) {
        return;
    }

    $_POST['payment_provider_id'] = $walletOperator->payment_provider_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('walletoperator.new')
                                   ->postUploadWalletOperatorLogo();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['payment_provider_id']);

    $walletOperator->setRelation('mediaLogo', $response->data);
    $walletOperator->media_logo = $response->data;
    $walletOperator->logo = $response->data[0]->path;
});


/**
 * Listen on:    `orbit.walletoperator.postupdatewalletoperator.after.save`
 * Purpose:      Handle file upload on wallet operator update
 *
 * @author kadek <kadek@dominopos.com>
 *
 * @param WalletOperatorAPIController $controller - The instance of the WalletOperatorAPIController or its subclass
 * @param WalletOperator $walletOperator - Instance of object Wallet Operator
 */
Event::listen('orbit.walletoperator.postupdatewalletoperator.after.save', function($controller, $walletOperator)
{
    $files = OrbitInput::files('logo');
    if (! $files) {
        return;
    }

    $_POST['payment_provider_id'] = $walletOperator->payment_provider_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('walletoperator.update')
                                   ->postUploadWalletOperatorLogo();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    $walletOperator->load('mediaLogo');
    $walletOperator->logo = $response->data[0]->path;
});