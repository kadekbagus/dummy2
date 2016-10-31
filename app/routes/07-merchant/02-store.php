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
