<?php

Route::get('/api/v1/cust/services', function()
{
    return Orbit\Controller\API\v1\Customer\ServiceCIAPIController::create()->getServiceList();
});

Route::get('/api/v1/cust/services/detail', function()
{
    return Orbit\Controller\API\v1\Customer\ServiceCIAPIController::create()->getServiceItem();
});

Route::get('/app/v1/cust/services', ['as' => 'customer-api-service-list', 'uses' => 'IntermediateCIAuthController@ServiceCI_getServiceList']);

Route::get('/app/v1/cust/services/detail', ['as' => 'customer-api-service-detail', 'uses' => 'IntermediateCIAuthController@ServiceCI_getServiceItem']);
