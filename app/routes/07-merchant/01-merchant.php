<?php

/**
 * List/Search merchant
 */
Route::get('/api/v1/merchant/merchant/{search}', function()
{
    return Orbit\Controller\API\v1\Merchant\Merchant\MerchantListAPIController::create()->getSearchMerchant();
})
->where('search', '(list|search)');

Route::get('/app/v1/merchant/merchant/{search}', ['as' => 'merchant-api-merchant-list', 'uses' => 'IntermediateMerchantAuthController@Merchant\MerchantList_getSearchMerchant'])
	->where('search', '(list|search)');

/**
 * List/Search merchant location
 */
Route::get('/api/v1/merchant/merchant/location/{search}', function()
{
    return Orbit\Controller\API\v1\Merchant\Merchant\MerchantLocationListAPIController::create()->getSearchMerchantLocation();
})
->where('search', '(list|search)');

Route::get('/app/v1/merchant/merchant/location/{search}', ['as' => 'merchant-api-merchant-location-list', 'uses' => 'IntermediateMerchantAuthController@Merchant\MerchantLocationList_getSearchMerchantLocation'])
	->where('search', '(list|search)');

/**
 * New merchant
 */
Route::post('/api/v1/merchant/merchant/new', function()
{
    return Orbit\Controller\API\v1\Merchant\Merchant\MerchantNewAPIController::create()->postNewMerchant();
});

Route::post('/app/v1/merchant/merchant/new', ['as' => 'merchant-api-merchant-new', 'uses' => 'IntermediateMerchantAuthController@Merchant\MerchantNew_postNewMerchant']);


/**
 * New merchant
 */
Route::post('/api/v1/merchant/merchant/upload-logo', function()
{
    return Orbit\Controller\API\v1\Merchant\Merchant\MerchantUploadLogoAPIController::create()->postUploadMerchantLogo();
});

Route::post('/app/v1/merchant/merchant/upload-logo', ['as' => 'merchant-api-merchant-upload-logo', 'uses' => 'IntermediateMerchantAuthController@Merchant\MerchantUploadLogo_postUploadMerchantLogo']);
