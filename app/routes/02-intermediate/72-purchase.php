<?php

Route::post('/app/v1/pub/purchase/new', [
    'as' => 'pub-digital-product-purchase-new',
    'uses' => 'IntermediatePubAuthController@Purchase\PurchaseNew_postNew'
]);

Route::get('/app/v1/pub/purchase/detail', [
    'as' => 'pub-digital-product-purchase-detail',
    'uses' => 'IntermediatePubAuthController@Purchase\PurchaseDetail_getDetail'
]);

Route::post('/app/v1/pub/purchase/update', [
    'as' => 'pub-digital-product-purchase-update',
    'uses' => 'IntermediatePubAuthController@Purchase\PurchaseUpdate_postUpdate'
]);
