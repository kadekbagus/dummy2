<?php
/**
 * Routes file for Wallet Operator related API
 */

/**
 * Create new wallet operator
 */
Route::post('/app/v1/wallet-operator/new', 'IntermediateAuthController@WalletOperator_postNewWalletOperator');