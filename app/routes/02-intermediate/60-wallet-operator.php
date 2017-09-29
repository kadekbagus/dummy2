<?php
/**
 * Routes file for Wallet Operator related API
 */

/**
 * Create new wallet operator
 */
Route::post('/app/v1/wallet-operator/new', 'IntermediateAuthController@WalletOperator_postNewWalletOperator');

/**
 * Update wallet operator
 */
Route::post('/app/v1/wallet-operator/update', 'IntermediateAuthController@WalletOperator_postUpdateWalletOperator');

/**
 * Get search wallet operator
 */
Route::get('/app/v1/wallet-operator/list', 'IntermediateAuthController@WalletOperator_getSearchWalletOperator');

/**
 * Get search banks
 */
Route::get('/app/v1/bank/list', 'IntermediateAuthController@WalletOperator_getSearchBank');