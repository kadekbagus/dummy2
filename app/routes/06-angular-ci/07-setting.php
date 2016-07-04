<?php

Route::get('/{api}/v1/cust/malls', function()
{
    return Orbit\Controller\API\v1\Customer\MallByDomainCIAPIController::create()->getMallIdByDomain();
})->where('api', '(api|app)');

// Route::get('/app/v1/cust/malls', ['as' => 'customer-api-object-list', 'uses' => 'IntermediateCIAuthController@MallByDomainCI_getMallIdByDomain']);
