<?php

/**
 * List of Pulsa
 */
Route::get('/api/v1/pub/pulsa/list', function()
{
    return Orbit\Controller\API\v1\Pub\Pulsa\PulsaListAPIController::create()->getList();
});

Route::get('/app/v1/pub/pulsa/list', ['as' => 'pub-pulsa-list', 'uses' => 'IntermediatePubAuthController@Pulsa\PulsaList_getList']);

Route::get('/api/v1/pub/pulsa/detail', function()
{
    return Orbit\Controller\API\v1\Pub\Pulsa\PulsaDetailAPIController::create()->getDetail();
});

Route::get('/app/v1/pub/pulsa/detail', ['as' => 'pub-pulsa-detail', 'uses' => 'IntermediatePubAuthController@Pulsa\PulsaDetail_getDetail']);

/**
 * List of Telco Operators
 */
Route::get('/api/v1/pub/telco-operator/list', function()
{
    return Orbit\Controller\API\v1\Pub\Pulsa\TelcoOperatorListAPIController::create()->getList();
});

Route::get('/app/v1/pub/telco-operator/list', ['as' => 'pub-pulsa-list', 'uses' => 'IntermediatePubAuthController@Pulsa\TelcoOperatorList_getList']);
