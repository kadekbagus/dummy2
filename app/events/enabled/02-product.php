<?php
/**
 * Event listener for Product related events.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:       `orbit.product.postnewproduct.after.save`
 *   Purpose:       Handle file upload on product creation
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @param MerchantAPIController $controller - The instance of the MerchantAPIController or its subclass
 * @param Merchant $product - Instance of object Merchant
 */
Event::listen('orbit.product.postnewproduct.after.save', function($controller, $product)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    $_POST['product_id'] = $product->product_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('product.new')
                                   ->postUploadProductImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['product_id']);

    $product->image = $response->data[0]->path;
});

/**
 * Listen on:       `orbit.product.postupdateproduct.after.save`
 *   Purpose:       Handle file upload on product update
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @param MerchantAPIController $controller - The instance of the MerchantAPIController or its subclass
 * @param Merchant $product - Instance of object Merchant
 */
Event::listen('orbit.product.postupdateproduct.after.save', function($controller, $product)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('product.update')
                                   ->postUploadProductImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    $product->setRelation('media', $response->data);
    $product->media = $response->data;
    $product->image = $response->data[0]->path;
});
