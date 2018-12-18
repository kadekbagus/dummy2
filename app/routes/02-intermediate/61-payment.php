<?php

Route::post('/app/v1/pub/payment/midtrans/create', ['as' => 'pub-payment-midtrans-create', 'uses' => 'IntermediatePubAuthController@Payment\PaymentMidtransCreate_postPaymentMidtransCreate']);

Route::post('/app/v1/pub/payment/midtrans/update', ['as' => 'pub-payment-midtrans-update', 'uses' => 'IntermediatePubAuthController@Payment\PaymentMidtransUpdate_postPaymentMidtransUpdate']);

Route::get('/app/v1/pub/payment/midtrans/verify', ['as' => 'pub-payment-midtrans-verify', 'uses' => 'IntermediatePubAuthController@Payment\PaymentMidtransVerify_getPaymentMidtransVerify']);

Route::post('/app/v1/pub/payment/stripe/create', ['as' => 'pub-payment-stripe-create', 'uses' => 'IntermediatePubAuthController@Payment\PaymentStripeCreate_postPaymentStripeCreate']);

Route::post('/app/v1/pub/payment/stripe/update', ['as' => 'pub-payment-stripe-update', 'uses' => 'IntermediatePubAuthController@Payment\PaymentStripeUpdate_postPaymentStripeUpdate']);

Route::get('/app/v1/pub/payment/stripe/verify', ['as' => 'pub-payment-stripe-verify', 'uses' => 'IntermediatePubAuthController@Payment\PaymentStripeVerify_getPaymentStripeVerify']);
