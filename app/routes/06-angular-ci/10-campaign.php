<?php

Route::get('/api/v1/cust/campaigns/pop-up', function()
{
    return Orbit\Controller\API\v1\Customer\CampaignCIAPIController::create()->getCampaignPopup();
});

Route::get('/app/v1/cust/campaigns/pop-up', ['as' => 'customer-api-campaign-popup', 'uses' => 'IntermediateCIAuthController@CampaignCI_getCampaignPopup']);

Route::post('/api/v1/cust/campaigns/pop-up/activity', function()
{
    return Orbit\Controller\API\v1\Customer\CampaignCIAPIController::create()->postCampaignPopUpActivities();
});

Route::post('/app/v1/cust/campaigns/pop-up/activity', ['as' => 'customer-api-campaign-popup-activity', 'uses' => 'IntermediateCIAuthController@CampaignCI_postCampaignPopUpActivities']);