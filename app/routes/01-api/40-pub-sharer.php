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
        Route::get('/pub/sharer/facebook/store-detail',
            array(
                'as' => 'pub-share-store',
                function()
                {
                    return Orbit\Controller\API\v1\Pub\SocMedAPIController::create()->getStoreDetailView();
                }
            )
        );
        Route::get('/pub/sharer/facebook/promotional-event-detail',
            array(
                'as' => 'pub-share-promotional-event',
                function()
                {
                    return Orbit\Controller\API\v1\Pub\SocMedAPIController::create()->getPromotionalEventDetailView();
                }
            )
        );
        Route::get('/pub/sharer/facebook/article-detail',
            array(
                'as' => 'pub-share-article',
                function()
                {
                    return Orbit\Controller\API\v1\Pub\SocMedAPIController::create()->getArticleDetailView();
                }
            )
        );
    }
);

Route::get('/pub/sharer',
    array(
        'as' => 'pub-sharer',
        function()
        {
            return Orbit\Controller\API\v1\Pub\LinkPreviewAPIController::create()->getDataPreview();
        }
    )
);


/**
 * share campaign via email
 */
Route::post('/api/v1/pub/sharer/email/campaign', function()
{
    return Orbit\Controller\API\v1\Pub\CampaignShareEmailAPIController::create()->postCampaignShareEmail();
});

Route::post('/app/v1/pub/sharer/email/campaign', ['as' => 'pub-share-email-campaign', 'uses' => 'IntermediatePubAuthController@CampaignShareEmail_postCampaignShareEmail']);

/**
 * share landing page via email
 */
Route::post('/api/v1/pub/sharer/email/landingpage', function()
{
    return Orbit\Controller\API\v1\Pub\LandingPageShareEmailAPIController::create()->postLandingPageShareEmail();
});

Route::post('/app/v1/pub/sharer/email/landingpage', ['as' => 'pub-share-email-landingpage', 'uses' => 'IntermediatePubAuthController@LandingPageShareEmail_postLandingPageShareEmail']);

/**
 * share landing page via AddThis
 */
Route::post('/api/v1/pub/share', function()
{
    return Orbit\Controller\API\v1\Pub\ShareAPIController::create()->postShare();
});

Route::post('/app/v1/pub/share', ['as' => 'pub-share', 'uses' => 'IntermediatePubAuthController@Share_postShare']);
