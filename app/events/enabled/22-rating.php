<?php
/**
 * Event listener for rating and review.
 *
 * @author Shelgi <shelgi@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;


/**
 * Listen on:    `orbit.rating.postnewrating.after.save`
 * Purpose:      Handle file upload on rating creation
 *
 * @param ratingNewAPIController $controller - The instance of the ratingNewAPIController or its subclass
 * @param rating $rating - Instance of object rating
 */
Event::listen('orbit.rating.postnewmedia', function($controller, $rating)
{
    $images = Input::file(null);
    if (! $images) {
        return;
    }

    // This will be used on MediaAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    // Use MediaAPIController class to upload the image
    $_POST['media_name_id'] = 'review_image';
    $_POST['object_id'] = $rating['object_id'];

    $response = MediaAPIController::create('raw')
        ->setEnableTransaction(false)
        ->upload();

    unset($_POST['media_name_id']);
    unset($_POST['object_id']);

    if ($response->code !== 0) {
        throw new \Exception($response->message, $response->code);
    }

    return $response->data;
});

/**
 * Listen on:       `orbit.rating.postupdaterating.after.save`
 *   Purpose:       Handle file upload on rating update
 *
 * @param ratingNewAPIController $controller - The instance of the ratingNewAPIController or its subclass
 * @param rating $rating - Instance of object rating
 */
Event::listen('orbit.rating.postdeletemedia', function($controller, $media)
{
    // This will be used on MediaAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    // Use MediaAPIController class to upload the new image
    $_POST['media_id'] = $media[0]->media_id;

    $response = MediaAPIController::create('raw')
        ->setEnableTransaction(false)
        ->delete();

    unset($_POST['media_id']);

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    return $response;
});


/**
 * Listen on:    `orbit.rating.postnewrating.after.commit`
 * Purpose:      Post actions after the data has been successfully commited
 *
 * @author Shelgi <shelgi@dominopos.com>
 *
 * @param RatingNewAPIController $controller - The instance of the RatingNewAPIController or its subclass
 * @param Advert $rating - Instance of object rating
 */
Event::listen('orbit.rating.postrating.after.commit', function($controller, $rating, $user)
{
    // update elasticsearch
    $objectType = $rating['object_type'];
    switch ($objectType) {
        case 'news':
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESNewsUpdateQueue', [
                'news_id' => $rating['object_id']
            ]);
            break;

        case 'promotion':
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESPromotionUpdateQueue', [
                'news_id' => $rating['object_id']
            ]);
            break;

        case 'coupon':
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', [
                'coupon_id' => $rating['object_id']
            ]);
            break;

        case 'store':
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESStoreUpdateQueue', [
                'name' => $rating['merchant_name'],
                'country' => $rating['country']
            ]);
            break;

        case 'mall':
            Queue::push('Orbit\\Queue\\Elasticsearch\\ESMallUpdateQueue', [
                'mall_id' => $rating['object_id']
            ]);
            break;
    }
});
