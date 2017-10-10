<?php

/**
 * List/Search Merchant Transaction Report List
 */
Route::get('/api/v1/merchant-transaction/reporting/{search}', function()
{
    return Orbit\Controller\API\v1\MerchantTransaction\MerchantTransactionReportAPIController::create()->getSearchMerchantTransactionReport();
})
->where('search', '(list|search)');

Route::get('/app/v1/merchant-transaction/reporting/{search}', ['as' => 'merchant-transaction-list', 'uses' => 'IntermediateMerchantTransactionAuthController@MerchantTransactionReport_getSearchMerchantTransactionReport'])
	->where('search', '(list|search)');