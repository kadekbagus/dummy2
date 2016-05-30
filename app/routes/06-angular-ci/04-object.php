<?php

Route::get('/api/v1/cust/floors', function()
{
    return Orbit\Controller\API\v1\Customer\ObjectCIAPIController::create()->getFloorList();
});

Route::get('/app/v1/cust/floors', ['as' => 'customer-api-object-list', 'uses' => 'IntermediateCIAuthController@ObjectCI_getFloorList']);
