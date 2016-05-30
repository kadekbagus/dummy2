<?php

Route::get('/api/v1/cust/categories', function()
{
    return Orbit\Controller\API\v1\Customer\CategoryCIAPIController::create()->getCategoryList();
});

Route::get('/app/v1/cust/categories', ['as' => 'customer-api-category-list', 'uses' => 'IntermediateCIAuthController@CategoryCI_getCategoryList']);
