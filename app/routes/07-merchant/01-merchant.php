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

/**
 * List/Search country
 */
Route::get('/api/v1/merchant/country/{search}', function()
{
    return Orbit\Controller\API\v1\Merchant\CountryListAPIController::create()->getSearchCountry();
})
->where('search', '(list|search)');

Route::get('/app/v1/merchant/country/{search}', ['as' => 'merchant-api-country-list', 'uses' => 'IntermediateMerchantAuthController@CountryList_getSearchCountry'])
	->where('search', '(list|search)');

/**
 * List/Search language
 */
Route::get('/api/v1/merchant/language/{search}', function()
{
    return Orbit\Controller\API\v1\Merchant\LanguageListAPIController::create()->getSearchLanguageList();
})
->where('search', '(list|search)');

Route::get('/app/v1/merchant/language/{search}', ['as' => 'merchant-api-language-list', 'uses' => 'IntermediateMerchantAuthController@LanguageList_getSearchLanguageList'])
    ->where('search', '(list|search)');

/**
 * Merchant Export
 */
Route::post('/api/v1/merchant/merchant-export', function()
{
    return Orbit\Controller\API\v1\Merchant\MerchantExportAPIController::create()->postMerchantExport();
});

Route::post('/app/v1/merchant/merchant-export', ['as' => 'merchant-export', 'uses' => 'IntermediateMerchantAuthController@MerchantExport_postMerchantExport']);


/**
 * List/Search bank
 */
Route::get('/api/v1/merchant/bank/{search}', function()
{
    return Orbit\Controller\API\v1\Merchant\BankListAPIController::create()->getSearchBank();
})
->where('search', '(list|search)');

Route::get('/app/v1/merchant/bank/{search}', ['as' => 'merchant-api-bank-list', 'uses' => 'IntermediateMerchantAuthController@BankList_getSearchBank'])
    ->where('search', '(list|search)');

    /**
 * List/Search payment provider
 */
Route::get('/api/v1/merchant/payment-provider/{search}', function()
{
    return Orbit\Controller\API\v1\Merchant\PaymentProviderListAPIController::create()->getSearchPaymentProvider();
})
->where('search', '(list|search)');

Route::get('/app/v1/merchant/payment-provider/{search}', ['as' => 'merchant-api-payment-provider-list', 'uses' => 'IntermediateMerchantAuthController@PaymentProviderList_getSearchPaymentProvider'])
    ->where('search', '(list|search)');


/**
 * Merchant Copy To Store
 */
Route::post('/api/v1/merchant/copy-to-store', function()
{
    return Orbit\Controller\API\v1\Merchant\Merchant\MerchantCopyToStoreAPIController::create()->postMerchantCopyToStore();
});

Route::post('/app/v1/merchant/copy-to-store', ['as' => 'merchant-copy-to-store', 'uses' => 'IntermediateMerchantAuthController@Merchant\MerchantCopyToStore_postMerchantCopyToStore']);
