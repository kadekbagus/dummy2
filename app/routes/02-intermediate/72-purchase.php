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

Route::get('/app/v1/pub/purchase/availability', [
    'as' => 'pub-digital-product-purchase-availability',
    'uses' => 'IntermediatePubAuthController@Purchase\PurchaseAvailability_getAvailability',
]);

Route::get('/app/v1/pub/order-purchased/detail', [
    'as' => 'pub-order-purchased-detail',
    'uses' => 'IntermediatePubAuthController@Order\OrderPurchasedDetail_handle'
]);

Route::get('/app/v1/pub/order-purchased/list', [
    'as' => 'pub-order-purchased-list',
    'uses' => 'IntermediatePubAuthController@Order\OrderPurchasedList_handle'
]);

Route::post('app/v1/pub/order-purchased/update-status', [
    'as' => 'pub-order-purchased-update-status',
    'uses' => 'IntermediatePubAuthController@Order\OrderStatusUpdate_handle',
]);

Route::post('app/v1/pub/purchase/bill-inquiry', [
    'as' => 'pub-bill-purchase-inquiry',
    'uses' => 'IntermediatePubAuthController@Purchase\PurchaseBillInquiry',
]);
