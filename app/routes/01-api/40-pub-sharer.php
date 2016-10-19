<?php

Route::group(
    array('before' => 'pub-fb-bot'),
    function () {
        Route::get('/pub/sharer/facebook/promotion-detail',
            array(
                'as' => 'pub-share-promotion',
                function()
                {
                    return Orbit\Controller\API\v1\Pub\SocMedAPIController::create()->getPromotionDetailView();
                }
            )
        );

        Route::get('/pub/sharer/facebook/news-detail',
            array(
                'as' => 'pub-share-news',
                function()
                {
                    return Orbit\Controller\API\v1\Pub\SocMedAPIController::create()->getNewsDetailView();
                }
            )
        );

        Route::get('/pub/sharer/facebook/coupon-detail',
            array(
                'as' => 'pub-share-coupon',
                function()
                {
                    return Orbit\Controller\API\v1\Pub\SocMedAPIController::create()->getCouponDetailView();
                }
            )
        );
    }
);


/**
 * share campaign via email
 */
Route::post(
    '/{search}/v1/pub/sharer/email/campaign', ['as' => 'pub-share-email-campaign', function()
    {
        return Orbit\Controller\API\v1\Pub\CampaignShareEmailAPIController::create()->postCampaignShareEmail();
    }]
)->where('search', '(api|app)');
