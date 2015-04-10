<?php
/**
 * Intermediate route for transaction history
 */

/**
 * List Merchants for particular user which has transactions
 */
Route::get('/app/v1/consumer-transaction-history/merchant-list', 'IntermediateAuthController@TransactionHistory_getMerchantList');

/**
 * List Retailers for particular user which has transactions
 */
Route::get('/app/v1/consumer-transaction-history/retailer-list', 'IntermediateAuthController@TransactionHistory_getRetailerList');

/**
 * List Products for particular user which has transactions
 */
Route::get('/app/v1/consumer-transaction-history/product-list', 'IntermediateAuthController@TransactionHistory_getProductList');
