<?php

/**
 * List/Search store
 */
Route::get('/api/v1/merchant/store/{search}', function()
{
    return Orbit\Controller\API\v1\Merchant\Store\StoreListAPIController::create()->getSearchStore();
})
->where('search', '(list|search)');

Route::get('/app/v1/merchant/store/{search}', ['as' => 'store-api-store-list', 'uses' => 'IntermediateMerchantAuthController@Store\StoreList_getSearchStore'])
	->where('search', '(list|search)');

/**
 * create store
 */
Route::post('/api/v1/merchant/store/new', function()
{
    return Orbit\Controller\API\v1\Merchant\Store\StoreNewAPIController::create()->postNewStore();
});

Route::post('/app/v1/merchant/store/new', ['as' => 'store-api-store-new', 'uses' => 'IntermediateMerchantAuthController@Store\StoreNew_postNewStore']);

/**
 * delete store image
 */
Route::post('/api/v1/merchant/store/image/delete', function()
{
    return Orbit\Controller\API\v1\Merchant\Store\StoreUploadAPIController::create()->postDeleteBaseStoreImage();
});

Route::post('/app/v1/merchant/store/image/delete', ['as' => 'store-api-store-image-delete', 'uses' => 'IntermediateMerchantAuthController@Store\StoreUpload_postDeleteBaseStoreImage']);

/**
 * delete store map
 */
Route::post('/api/v1/merchant/store/map/delete', function()
{
    return Orbit\Controller\API\v1\Merchant\Store\StoreUploadAPIController::create()->postDeleteBaseStoreMap();
});

Route::post('/app/v1/merchant/store/map/delete', ['as' => 'store-api-store-map-delete', 'uses' => 'IntermediateMerchantAuthController@Store\StoreUpload_postDeleteBaseStoreMap']);

/**
 * update store
 */
Route::post('/api/v1/merchant/store/update', function()
{
    return Orbit\Controller\API\v1\Merchant\Store\StoreUpdateAPIController::create()->postUpdateStore();
});

Route::post('/app/v1/merchant/store/update', ['as' => 'store-api-store-update', 'uses' => 'IntermediateMerchantAuthController@Store\StoreUpdate_postUpdateStore']);
