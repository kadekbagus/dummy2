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
Event::listen('orbit.brandproduct.postnewbrandproduct.after.save', function($product, $onlineProduct)
{
    // This will be used on MediaAPIController
    $media = [];
    $maxPhotos = 4;
    $user = App::make('currentUser');
    App::instance('orbit.upload.user', $user);
    $_POST['media_name_id'] = 'brand_product_main_photo';
    $_POST['object_id'] = $product->brand_product_id;

    // Process brand product main photo
    $brand_product_main_photo = OrbitInput::files('brand_product_main_photo');

    if (! empty($brand_product_main_photo)) {
        // Use MediaAPIController class to upload the image

        $response = MediaAPIController::create('raw')
                                    ->setEnableTransaction(false)
                                    ->setInputName('brand_product_main_photo')
                                    ->setSkipRoleChecking()
                                    ->upload();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $media[] = $response->data;

        // product main photos for online product
        if (isset($onlineProduct->product_id)) {

            $_POST['media_name_id'] = 'product_image';
            $_POST['object_id'] = $onlineProduct->product_id;
    
            $response = MediaAPIController::create('raw')
                                        ->setEnableTransaction(false)
                                        ->setInputName('brand_product_main_photo')
                                        ->setSkipRoleChecking()
                                        ->upload();
    
            if ($response->code !== 0)
            {
                throw new \Exception($response->message, $response->code);
            }
    
            $mediaOnlineProduct[] = $response->data;
    
            unset($_POST['media_name_id']);
            unset($_POST['object_id']);
        }
    }

    // Process brand product photos...
    $_POST['media_name_id'] = 'brand_product_photos';
    $_POST['object_id'] = $product->brand_product_id;

    for($i = 0; $i < $maxPhotos; $i++) {

        $inputName = "images{$i}";
        if (Request::hasFile($inputName)) {

            $response = MediaAPIController::create('raw')
                                        ->setInputName($inputName)
                                        ->setEnableTransaction(false)
                                        ->setSkipRoleChecking()
                                        ->upload();

            if ($response->code !== 0)
            {
                throw new \Exception($response->message, $response->code);
            }

            $media[] = $response->data;
        }
    }

    $product->setRelation('media', $media);
    $product->media = $media;

    if (isset($media[0])) {
        $product->imagePath = $media[0][0]->variants[0]->path;
    }

    unset($_POST['media_name_id']);
    unset($_POST['object_id']);
 
    // product photos for online product
    if (isset($onlineProduct->product_id)) {
        
        $_POST['media_name_id'] = 'product_photos';
        $_POST['object_id'] = $onlineProduct->product_id;
    
        for($i = 0; $i < $maxPhotos; $i++) {
    
            $inputName = "images{$i}";
            if (Request::hasFile($inputName)) {
    
                $response = MediaAPIController::create('raw')
                                            ->setInputName($inputName)
                                            ->setEnableTransaction(false)
                                            ->setSkipRoleChecking()
                                            ->upload();
    
                if ($response->code !== 0)
                {
                    throw new \Exception($response->message, $response->code);
                }
    
                $mediaOnlineProduct[] = $response->data;
            }
        }
    
        $onlineProduct->media = $mediaOnlineProduct;

        unset($_POST['media_name_id']);
        unset($_POST['object_id']);
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