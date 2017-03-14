<?php


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
