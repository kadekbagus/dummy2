<?php
/**
 * Routes file for campaign
 */

/**
 * Get list of campaign slider / pop up
 */
Route::get('/api/v1/pub/campaign-slider', function()
{
    return Orbit\Controller\API\v1\Pub\CampaignSliderAPIController::create()->getCampaignSlider();
});

Route::get('/app/v1/pub/campaign-slider', ['as' => 'campaign-slider', 'uses' => 'IntermediatePubAuthController@CampaignSlider_getCampaignSlider']);
