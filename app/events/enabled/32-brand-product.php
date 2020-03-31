<?php
/**
 * Event listener for Brand Product related events.
 *
 * @author kade <kadek@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:    `orbit.brandproduct.postnewbrandproduct.after.save`
 * Purpose:      Handle file upload on product creation
 *
 * @param ProductNewAPIController $controller - The instance of the ProductNewAPIController or its subclass
 * @param Product $product - Instance of object Product
 */
Event::listen('orbit.brandproduct.postnewbrandproduct.after.save', function($product)
{
    $brand_product_main_photo = OrbitInput::files('brand_product_main_photo');

    if (! empty($brand_product_main_photo)) {

        $user = App::make('currentUser');
        // This will be used on MediaAPIController
        App::instance('orbit.upload.user', $user);

        // Use MediaAPIController class to upload the image
        $_POST['media_name_id'] = 'brand_product_main_photo';
        $_POST['object_id'] = $product->brand_product_id;

        $response = MediaAPIController::create('raw')
                                    ->setEnableTransaction(false)
                                    ->setInputName('brand_product_main_photo')
                                    ->setSkipRoleChecking()
                                    ->upload();

        unset($_POST['media_name_id']);
        unset($_POST['object_id']);


        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $product->setRelation('media', $response->data);
        $product->media = $response->data;
        $product->imagePath = $response->data[0]->variants[0]->path;
    }

    // image1,2,3,4
    $images1 = OrbitInput::files('images1');
    if (! empty($images1)) {

        $user = App::make('currentUser');
        // This will be used on MediaAPIController
        App::instance('orbit.upload.user', $user);

        // Use MediaAPIController class to upload the image
        $_POST['media_name_id'] = 'brand_product_photos';
        $_POST['object_id'] = $product->brand_product_id;

        $response = MediaAPIController::create('raw')
                                    ->setEnableTransaction(false)
                                    ->setInputName('images1')
                                    ->setSkipRoleChecking()
                                    ->upload();

        unset($_POST['media_name_id']);
        unset($_POST['object_id']);


        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $product->setRelation('media', $response->data);
        $product->media = $response->data;
        $product->imagePath = $response->data[0]->variants[0]->path;
    }

    $images2 = OrbitInput::files('images2');
    if (! empty($images2)) {

        $user = App::make('currentUser');
        // This will be used on MediaAPIController
        App::instance('orbit.upload.user', $user);

        // Use MediaAPIController class to upload the image
        $_POST['media_name_id'] = 'brand_product_photos';
        $_POST['object_id'] = $product->brand_product_id;

        $response = MediaAPIController::create('raw')
                                    ->setEnableTransaction(false)
                                    ->setInputName('images2')
                                    ->setSkipRoleChecking()
                                    ->upload();

        unset($_POST['media_name_id']);
        unset($_POST['object_id']);


        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $product->setRelation('media', $response->data);
        $product->media = $response->data;
        $product->imagePath = $response->data[0]->variants[0]->path;
    }

    $images3 = OrbitInput::files('images3');
    if (! empty($images3)) {

        $user = App::make('currentUser');
        // This will be used on MediaAPIController
        App::instance('orbit.upload.user', $user);

        // Use MediaAPIController class to upload the image
        $_POST['media_name_id'] = 'brand_product_photos';
        $_POST['object_id'] = $product->brand_product_id;

        $response = MediaAPIController::create('raw')
                                    ->setEnableTransaction(false)
                                    ->setInputName('images3')
                                    ->setSkipRoleChecking()
                                    ->upload();

        unset($_POST['media_name_id']);
        unset($_POST['object_id']);


        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $product->setRelation('media', $response->data);
        $product->media = $response->data;
        $product->imagePath = $response->data[0]->variants[0]->path;
    }

    $images4 = OrbitInput::files('images4');
    if (! empty($images4)) {

        $user = App::make('currentUser');
        // This will be used on MediaAPIController
        App::instance('orbit.upload.user', $user);

        // Use MediaAPIController class to upload the image
        $_POST['media_name_id'] = 'brand_product_photos';
        $_POST['object_id'] = $product->brand_product_id;

        $response = MediaAPIController::create('raw')
                                    ->setEnableTransaction(false)
                                    ->setInputName('images4')
                                    ->setSkipRoleChecking()
                                    ->upload();

        unset($_POST['media_name_id']);
        unset($_POST['object_id']);


        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $product->setRelation('media', $response->data);
        $product->media = $response->data;
        $product->imagePath = $response->data[0]->variants[0]->path;
    }

});

/**
 * Listen on:    `orbit.brandproduct.after.commit`
 * Purpose:      Handle file upload on product creation
 *
 * @param ProductNewAPIController $controller - The instance of the ProductNewAPIController or its subclass
 * @param Product $product - Instance of object Product
 */
Event::listen(
    'orbit.brandproduct.after.commit',
    function($brandProductId) {
        Queue::push(
            'Orbit\Queue\Elasticsearch\ESBrandProductUpdateQueue',
            ['brand_product_id' => $brandProductId]
        );
    }
);