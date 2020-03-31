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
    $image1 = OrbitInput::files('image1');
    if (! empty($image1)) {

        $user = App::make('currentUser');
        // This will be used on MediaAPIController
        App::instance('orbit.upload.user', $user);

        // Use MediaAPIController class to upload the image
        $_POST['media_name_id'] = 'brand_product_photos';
        $_POST['object_id'] = $product->brand_product_id;

        $response = MediaAPIController::create('raw')
                                    ->setEnableTransaction(false)
                                    ->setInputName('image1')
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

    $image2 = OrbitInput::files('image2');
    if (! empty($image2)) {

        $user = App::make('currentUser');
        // This will be used on MediaAPIController
        App::instance('orbit.upload.user', $user);

        // Use MediaAPIController class to upload the image
        $_POST['media_name_id'] = 'brand_product_photos';
        $_POST['object_id'] = $product->brand_product_id;

        $response = MediaAPIController::create('raw')
                                    ->setEnableTransaction(false)
                                    ->setInputName('image2')
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

    $image3 = OrbitInput::files('image3');
    if (! empty($image3)) {

        $user = App::make('currentUser');
        // This will be used on MediaAPIController
        App::instance('orbit.upload.user', $user);

        // Use MediaAPIController class to upload the image
        $_POST['media_name_id'] = 'brand_product_photos';
        $_POST['object_id'] = $product->brand_product_id;

        $response = MediaAPIController::create('raw')
                                    ->setEnableTransaction(false)
                                    ->setInputName('image3')
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

    $image4 = OrbitInput::files('image4');
    if (! empty($image4)) {

        $user = App::make('currentUser');
        // This will be used on MediaAPIController
        App::instance('orbit.upload.user', $user);

        // Use MediaAPIController class to upload the image
        $_POST['media_name_id'] = 'brand_product_photos';
        $_POST['object_id'] = $product->brand_product_id;

        $response = MediaAPIController::create('raw')
                                    ->setEnableTransaction(false)
                                    ->setInputName('image4')
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