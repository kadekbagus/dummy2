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
Route::post('/api/v1/merchant/merchant/update', function()
{
    return Orbit\Controller\API\v1\Merchant\Merchant\MerchantUpdateAPIController::create()->postUpdateMerchant();
});

Route::post('/app/v1/merchant/merchant/update', ['as' => 'merchant-api-merchant-update', 'uses' => 'IntermediateMerchantAuthController@Merchant\MerchantUpdate_postUpdateMerchant']);

/**
 * Get merchant detail
 */
Route::get('/api/v1/merchant/merchant/detail', function()
{
    return Orbit\Controller\API\v1\Merchant\Merchant\MerchantDetailAPIController::create()->getMerchantDetail();
});

Route::get('/app/v1/merchant/merchant/detail', ['as' => 'merchant-api-merchant-detail', 'uses' => 'IntermediateMerchantAuthController@Merchant\MerchantDetail_getMerchantDetail']);

/**
 * Get merchant detail
 */
Route::get('/api/v1/merchant/merchant/partner', function()
{
    return Orbit\Controller\API\v1\Merchant\Merchant\MerchantPartnerAPIController::create()->getMerchantPartner();
});

Route::get('/app/v1/merchant/merchant/partner', ['as' => 'merchant-api-merchant-partner', 'uses' => 'IntermediateMerchantAuthController@Merchant\MerchantPartner_getMerchantPartner']);
