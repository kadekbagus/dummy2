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

/**
 * Electricity purchased detail api
 */
Route::get('/app/v1/pub/electricity-purchased/detail', ['as' => 'pub-electricity-purchased-detail', 'uses' => 'IntermediatePubAuthController@DigitalProduct\ElectricityPurchasedDetail_getElectricityPurchasedDetail']);

/**
 * Electricity Bill purchased list api
 */
Route::get('/app/v1/pub/electricity-bill-purchased/list', ['as' => 'pub-electricity-bill-purchased-list', 'uses' => 'IntermediatePubAuthController@DigitalProduct\ElectricityBillPurchasedList_getElectricityBillPurchasedList']);

/**
 * Electricity Bill purchased detail api
 */
Route::get('/app/v1/pub/electricity-bill-purchased/detail', ['as' => 'pub-electricity-bill-purchased-detail', 'uses' => 'IntermediatePubAuthController@DigitalProduct\ElectricityBillPurchasedDetail_getElectricityBillPurchasedDetail']);

/**
 * PDAM Bill purchased list api
 */
Route::get('/app/v1/pub/pdam-bill-purchased/list', ['as' => 'pub-pdam-bill-purchased-list', 'uses' => 'IntermediatePubAuthController@DigitalProduct\PDAMBillPurchasedList_getPDAMBillPurchasedList']);

/**
 * PDAM Bill purchased detail api
 */
Route::get('/app/v1/pub/pdam-bill-purchased/detail', ['as' => 'pub-pdam-bill-purchased-detail', 'uses' => 'IntermediatePubAuthController@DigitalProduct\PDAMBillPurchasedDetail_getPDAMBillPurchasedDetail']);

/**
 * PBB Tax purchased list api
 */
Route::get('/app/v1/pub/pbb-tax-purchased/list', ['as' => 'pub-pbb-tax-purchased-list', 'uses' => 'IntermediatePubAuthController@DigitalProduct\PBBTaxPurchasedList_getPBBTaxPurchasedList']);

/**
 * PBB Tax purchased detail api
 */
Route::get('/app/v1/pub/pbb-tax-purchased/detail', ['as' => 'pub-pbb-tax-purchased-detail', 'uses' => 'IntermediatePubAuthController@DigitalProduct\PBBTaxPurchasedDetail_getPBBTaxPurchasedDetail']);

/**
 * BPJS Bill purchased list api
 */
Route::get('/app/v1/pub/bpjs-bill-purchased/list', ['as' => 'pub-bpjs-bill-purchased-list', 'uses' => 'IntermediatePubAuthController@DigitalProduct\BpjsBillPurchasedList_getBpjsBillPurchasedList']);

/**
 * BPJS Bill purchased detail api
 */
Route::get('/app/v1/pub/bpjs-bill-purchased/detail', ['as' => 'pub-bpjs-bill-purchased-detail', 'uses' => 'IntermediatePubAuthController@DigitalProduct\BpjsBillPurchasedDetail_getBpjsBillPurchasedDetail']);

/**
 * Internet Provider Bill purchased list api
 */
Route::get('/app/v1/pub/internet-provider-bill-purchased/list', ['as' => 'pub-internet-provider-bill-purchased-list', 'uses' => 'IntermediatePubAuthController@DigitalProduct\InternetProviderBillPurchasedList_getInternetProviderBillPurchasedList']);

/**
 * Internet Provider Bill purchased detail api
 */
Route::get('/app/v1/pub/internet-provider-bill-purchased/detail', ['as' => 'pub-internet-provider-bill-purchased-detail', 'uses' => 'IntermediatePubAuthController@DigitalProduct\InternetProviderBillPurchasedDetail_getInternetProviderBillPurchasedDetail']);