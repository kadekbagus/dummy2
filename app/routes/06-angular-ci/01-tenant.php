<?php

Route::get('/api/v1/cust/stores', function()
{
    return Orbit\Controller\API\v1\Customer\TenantCIAPIController::create()->getTenantList();
});

Route::get('/api/v1/cust/stores/{id}', function($id)
{
    return Orbit\Controller\API\v1\Customer\TenantCIAPIController::create()->getTenantList();
});

Route::get('/app/v1/cust/stores', ['as' => 'customer-api-store-list', 'uses' => 'IntermediateCIAuthController@TenantCI_getTenantList']);

Route::get('/app/v1/cust/stores/{id}', ['as' => 'customer-api-store-detail', 'uses' => 'IntermediateCIAuthController@TenantCI_getTenantItem']);
