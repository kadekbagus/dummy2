<?php

Route::get('/api/v1/cust/my-account', function()
{
    return Orbit\Controller\API\v1\Customer\UserCIAPIController::create()->getMyAccountInfo();
});

Route::get('/app/v1/cust/my-account', ['as' => 'customer-api-my-account', 'uses' => 'IntermediateCIAuthController@UserCI_getMyAccountInfo']);


Route::post('/api/v1/cust/edit-account', function()
{
    return Orbit\Controller\API\v1\Customer\UserCIAPIController::create()->postEditAccount();
});

Route::post('/app/v1/cust/edit-account', ['as' => 'customer-api-edit-account', 'uses' => 'IntermediateCIAuthController@UserCI_postEditAccount']);