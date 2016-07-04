<?php

Route::get('/api/v1/cust/campaigns/pop-up', function()
{
    return Orbit\Controller\API\v1\Customer\CampaignCIAPIController::create()->getCampaignPopup();
});

Route::get('/app/v1/cust/campaigns/pop-up', ['as' => 'customer-api-campaign-popup', 'uses' => 'IntermediateCIAuthController@CampaignCI_getCampaignPopup']);
