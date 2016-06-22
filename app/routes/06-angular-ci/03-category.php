<?php

Route::get('/api/v1/cust/categories', function()
{
    return Orbit\Controller\API\v1\Customer\CategoryCIAPIController::create()->getCategoryList();
});

Route::get('/api/v1/cust/categories-service', function()
{
    return Orbit\Controller\API\v1\Customer\CategoryCIAPIController::create()->getCategoryServiceList();
});

Route::get('/app/v1/cust/categories', ['as' => 'customer-api-category-list', 'uses' => 'IntermediateCIAuthController@CategoryCI_getCategoryList']);

Route::get('/app/v1/cust/categories-service', ['as' => 'customer-api-category-service-list', 'uses' => 'IntermediateCIAuthController@CategoryCI_getCategoryServiceList']);
