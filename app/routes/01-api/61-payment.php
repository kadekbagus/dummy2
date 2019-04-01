<?php
/**
 * Routes file for Payment related API
 */

/**
 * Get ping payment
 */
Route::get('/api/v1/pub/payment/ping', function()
{
    return Orbit\Controller\API\v1\Pub\PingPaymentAPIController::create()->getPingPayment();
});

Route::get('/app/v1/pub/payment/ping', ['as' => 'ping-payment', 'uses' => 'IntermediatePubAuthController@PingPayment_getPingPayment']);

/**
 * Callback url that will be ping-ed by our Payment Api server.
 */
Route::post('/app/v1/pub/payment/activity-callback', function()
{
    return Orbit\Controller\API\v1\Pub\PaymentActivityAPIController::create()->postNewPaymentActivity();
});


Route::post('/api/v1/pub/payment/midtrans/create', function()
{
    return Orbit\Controller\API\v1\Pub\Payment\PaymentMidtransCreateAPIController::create()->postPaymentMidtransCreate();
});

Route::post('/api/v1/pub/payment/midtrans/update', function()
{
    return Orbit\Controller\API\v1\Pub\Payment\PaymentMidtransUpdateAPIController::create()->postPaymentMidtransUpdate();
});

Route::get('/api/v1/pub/payment/midtrans/verify', function()
{
    return Orbit\Controller\API\v1\Pub\Payment\PaymentMidtransVerifyAPIController::create()->getPaymentMidtransVerify();
});


/**
 * pulsa purchased list
 */
Route::get('/api/v1/pub/pulsa-purchased/list', function()
{
    return Orbit\Controller\API\v1\Pub\Payment\PaymentPulsaPurchasedListAPIController::create()->getPulsaPurchasedList();
});

Route::get('/app/v1/pub/pulsa-purchased/list', ['as' => 'pub-pulsa-purchased-list', 'uses' => 'IntermediatePubAuthController@Payment\PaymentPulsaPurchasedList_getPulsaPurchasedList']);


/**
 * pulsa purchased transaction detail
 */
Route::get('/api/v1/pub/pulsa-purchased/detail', function()
{
    return Orbit\Controller\API\v1\Pub\Pulsa\PulsaPurchasedDetailAPIController::create()->getPulsaPurchasedDetail();
});

Route::get('/app/v1/pub/pulsa-purchased/detail', ['as' => 'pub-pulsa-purchased-detail', 'uses' => 'IntermediatePubAuthController@Pulsa\PulsaPurchasedDetail_getPulsaPurchasedDetail']);
