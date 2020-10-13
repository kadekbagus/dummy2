<?php

/**
 * Game list api
 */
Route::get('/app/v1/pub/game/list', ['as' => 'game-list', 'uses' => 'IntermediatePubAuthController@DigitalProduct\GameList_getList']);

/**
 * Game list api
 */
Route::get('/app/v1/pub/game/detail', ['as' => 'game-detail', 'uses' => 'IntermediatePubAuthController@DigitalProduct\GameDetail_getDetail']);

/**
 * Digital product detail api.
 */
Route::get('/app/v1/pub/digital-product/detail', ['as' => 'pub-digital-product-detail', 'uses' => 'IntermediatePubAuthController@DigitalProduct\DigitalProductDetail_getDetail']);


/**
 * Game Voucher Purchased list.
 */
Route::get('/app/v1/pub/game-voucher-purchased/list', ['as' => 'pub-game-voucher-purchased-list', 'uses' => 'IntermediatePubAuthController@DigitalProduct\GameVoucherPurchasedList_getGameVoucherPurchasedList']);


/**
 * Game Voucher Purchased detail.
 */
Route::get('/app/v1/pub/game-voucher-purchased/detail', ['as' => 'pub-game-voucher-purchased-detail', 'uses' => 'IntermediatePubAuthController@DigitalProduct\GameVoucherPurchasedDetail_getGameVoucherPurchasedDetail']);

/**
 * Electricity list api
 */
Route::get('/app/v1/pub/electricity/list', ['as' => 'pub-electricity-list', 'uses' => 'IntermediatePubAuthController@DigitalProduct\ElectricityList_getList']);

/**
 * Electricity purchased list api
 */
Route::get('/app/v1/pub/electricity-purchased/list', ['as' => 'pub-electricity-purchased-list', 'uses' => 'IntermediatePubAuthController@DigitalProduct\ElectricityPurchasedList_getElectricityPurchasedList']);
