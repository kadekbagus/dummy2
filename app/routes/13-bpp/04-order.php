<?php

// Route for order list
Route::get('/app/v1/brand-product/order/list', ['as' => 'brand-product-order-list', 'uses' => 'IntermediateBrandProductAuthController@Order\OrderList_getSearchOrder']);

// Route for order detail
Route::get('/app/v1/brand-product/order/detail', ['as' => 'brand-product-order-detail', 'uses' => 'IntermediateBrandProductAuthController@Order\OrderDetail_getOrderDetail']);

// Route for order update status
Route::post('/app/v1/brand-product/order/update-status', ['as' => 'brand-product-order-update-status', 'uses' => 'IntermediateBrandProductAuthController@Order\OrderUpdateStatus_postUpdate']);