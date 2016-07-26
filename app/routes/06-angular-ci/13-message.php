<?php

Route::get('/api/v1/cust/messages', function()
{
    return Orbit\Controller\API\v1\Customer\MessageCIAPIController::create()->getMessage();
});

Route::get('/api/v1/cust/messages/detail', function()
{
    return Orbit\Controller\API\v1\Customer\MessageCIAPIController::create()->getMessageDetail();
});

Route::post('/api/v1/cust/messages/delete', function()
{
    return Orbit\Controller\API\v1\Customer\MessageCIAPIController::create()->postDeleteMessage();
});

Route::get('/app/v1/cust/messages', ['as' => 'messages-list', 'uses' => 'IntermediateCIAuthController@MessageCI_getMessage']);

Route::get('/app/v1/cust/messages/detail', ['as' => 'messages-detail', 'uses' => 'IntermediateCIAuthController@MessageCI_getMessageDetail']);

Route::post('/app/v1/cust/messages/delete', 'IntermediateCIAuthController@MessageCI_postDeleteMessage');