<?php
/**
 * Event listener for rating and review.
 *
 * @author Shelgi <shelgi@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:    `orbit.rating.postnewrating.after.commit`
 * Purpose:      Post actions after the data has been successfully commited
 *
 * @author Shelgi <shelgi@dominopos.com>
 *
 * @param RatingNewAPIController $controller - The instance of the RatingNewAPIController or its subclass
 * @param Advert $rating - Instance of object rating
 */
Event::listen('orbit.rating.postrating.after.commit', function($controller, $rating)
{
    // send email if review text is not empty
    if (! empty($rating['review'])) {
        Queue::push('Orbit\\Queue\\RatingAndReviewMailQueue', $rating, 'review_email');
    }

    // update elasticsearch
    // $objectType = $rating['object_type'];
    // switch ($objectType) {
    //     case 'news':
    //         Queue::push('Orbit\\Queue\\Elasticsearch\\ESNewsUpdateQueue', [
    //             'news_id' => $rating['object_id']
    //         ]);
    //         break;

    //     case 'promotion':
    //         Queue::push('Orbit\\Queue\\Elasticsearch\\ESPromotionUpdateQueue', [
    //             'news_id' => $rating['object_id']
    //         ]);
    //         break;

    //     case 'coupon':
    //         Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', [
    //             'coupon_id' => $rating['object_id']
    //         ]);
    //         break;

    //     case 'store':
    //         Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', [
    //             'name' => $rating['merchant_name'],
    //             'country' => $rating['country']
    //         ]);
    //         break;

    //     case 'mall':
    //         Queue::push('Orbit\\Queue\\Elasticsearch\\ESMallUpdateQueue', [
    //             'mall_id' => $rating['object_id']
    //         ]);
    //         break;
    // }
});