<?php

/**
 * List/Search merchant
 */
Route::get('/{app}/v1/merchant/merchant/{search}', ['as' => 'customer-api-store-detail', 'uses' => 'IntermediateMerchantAuthController@Merchant\MerchantList_getSearchMerchant'])
	->where([
		'app' => '(app|api)',
		'search' => '(list|search)'
	]
);
