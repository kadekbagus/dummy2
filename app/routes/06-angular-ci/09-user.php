<?php

Route::get('/api/v1/cust/my-account', function()
{
    return Orbit\Controller\API\v1\Customer\UserCIAPIController::create()->getMyAccountInfo();
});

Route::get('/app/v1/cust/my-account', ['as' => 'customer-api-my-account', 'uses' => 'IntermediateCIAuthController@UserCI_getMyAccountInfo']);

Route::post('/api/v1/cust/checkin', function()
{
    return Orbit\Controller\API\v1\Customer\CheckInCIAPIController::create()->postCekSignIn();
});

Route::post('/app/v1/cust/checkin', ['as' => 'customer-cek-sign-in', 'uses' => 'IntermediateCIAuthController@CheckInCI_postCekSignIn']);


Route::post('/api/v1/pub/edit-account', function()
{
    return Orbit\Controller\API\v1\Pub\UserAPIController::create()->postEditAccount();
});

Route::post('/app/v1/pub/edit-account', ['as' => 'pub-edit-account', 'uses' => 'IntermediatePubAuthController@User_postEditAccount']);
