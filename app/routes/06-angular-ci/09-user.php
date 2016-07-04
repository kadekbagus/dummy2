<?php

Route::get('/api/v1/cust/my-account', function()
{
    return Orbit\Controller\API\v1\Customer\UserCIAPIController::create()->getMyAccountInfo();
});

Route::get('/app/v1/cust/my-account', ['as' => 'customer-api-my-account', 'uses' => 'IntermediateCIAuthController@UserCI_getMyAccountInfo']);
