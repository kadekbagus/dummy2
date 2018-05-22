<?php

Route::post('/app/v1/pub/payment/midtrans/create', ['as' => 'pub-payment-midtrans-create', 'uses' => 'IntermediatePubAuthController@Payment\PaymentMidtransCreate_postPaymentMidtransCreate']);

Route::post('/app/v1/pub/payment/midtrans/update', ['as' => 'pub-payment-midtrans-update', 'uses' => 'IntermediatePubAuthController@Payment\PaymentMidtransUpdate_postPaymentMidtransUpdate']);

Route::post('/app/v1/pub/payment/midtrans/verify', ['as' => 'pub-payment-midtrans-verify', 'uses' => 'IntermediatePubAuthController@Payment\PaymentMidtransVerify_postPaymentMidtransVerify']);