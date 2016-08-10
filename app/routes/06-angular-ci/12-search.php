<?php

Route::get('/api/v1/cust/keyword/search', function()
{
    return Orbit\Controller\API\v1\Customer\PowerSearchCIAPIController::create()->getPowerSearch();
});

Route::get('/app/v1/cust/keyword/search', ['as' => 'customer-api-search', 'uses' => 'IntermediateCIAuthController@PowerSearchCI_getPowerSearch']);