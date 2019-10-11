<?php

/**
 * List of Pulsa
 */
Route::get('/api/v1/pub/pulsa/list', function()
{
    return Orbit\Controller\API\v1\Pub\Pulsa\PulsaListAPIController::create()->getList();
});

Route::get('/app/v1/pub/pulsa/list', ['as' => 'pub-pulsa-list', 'uses' => 'IntermediatePubAuthController@Pulsa\PulsaList_getList']);

/**
 * List of Pulsa
 */
Route::get('/api/v1/pub/pulsa/check', function()
{
    return Orbit\Controller\API\v1\Pub\CheckPulsaListAPIController::create()->getList();
});

Route::get('/app/v1/pub/pulsa/check', ['as' => 'pub-pulsa-check', 'uses' => 'IntermediatePubAuthController@CheckPulsaList_getList']);

Route::get('/api/v1/pub/pulsa/detail', function()
{
    return Orbit\Controller\API\v1\Pub\Pulsa\PulsaDetailAPIController::create()->getDetail();
});

Route::get('/app/v1/pub/pulsa/detail', ['as' => 'pub-pulsa-detail', 'uses' => 'IntermediatePubAuthController@Pulsa\PulsaDetail_getDetail']);

Route::post('/api/v1/pub/pulsa/availability', function()
{
    return Orbit\Controller\API\v1\Pub\Pulsa\PulsaAvailabilityAPIController::create()->postAvailability();
});

Route::post('/app/v1/pub/pulsa/availability', ['as' => 'pub-pulsa-availability', 'uses' => 'IntermediatePubAuthController@Pulsa\PulsaAvailability_postAvailability']);

/**
 * Subscribe to pulsa.
 */
Route::post('/api/v1/pub/pulsa/subscription', function()
{
    return Orbit\Controller\API\v1\Pub\Pulsa\PulsaSubscriptionAPIController::create()->postSubscription();
});

Route::post('/app/v1/pub/pulsa/subscription', ['as' => 'pub-pulsa-subscription', 'uses' => 'IntermediatePubAuthController@Pulsa\PulsaSubscription_postSubscription']);

/**
 * List of Telco Operators
 */
Route::get('/api/v1/pub/telco-operator/list', function()
{
    return Orbit\Controller\API\v1\Pub\Pulsa\TelcoOperatorListAPIController::create()->getList();
});

Route::get('/app/v1/pub/telco-operator/list', ['as' => 'pub-pulsa-list', 'uses' => 'IntermediatePubAuthController@Pulsa\TelcoOperatorList_getList']);
