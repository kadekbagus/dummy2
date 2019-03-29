<?php

/**
 * Pulsa create
 */
Route::post('/api/v1/pulsa/new', function()
{
    return PulsaAPIController::create()->postNewPulsa();
});

/**
 * Pulsa update
 */
Route::post('/api/v1/pulsa/update', function()
{
    return PulsaAPIController::create()->postUpdatePulsa();
});

/**
 * Pulsa list
 */
Route::get('/api/v1/pulsa/list', function()
{
    return PulsaAPIController::create()->getSearchPulsa();
});

/**
 * Pulsa Detail
 */
Route::get('/api/v1/pulsa/detail', function()
{
    return PulsaAPIController::create()->getDetailPulsa();
});

/**
 * pulsa purchased transaction detail
 */
Route::get('/api/v1/pub/pulsa-purchased/detail', function()
{
    return Orbit\Controller\API\v1\Pub\Pulsa\PulsaPurchasedDetailAPIController::create()->getPulsaPurchasedDetail();
});

Route::get('/app/v1/pub/pulsa-purchased/detail', ['as' => 'pub-pulsa-purchased-detail', 'uses' => 'IntermediatePubAuthController@Pulsa\PulsaPurchasedDetail_getPulsaPurchasedDetail']);
