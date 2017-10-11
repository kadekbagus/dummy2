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
