<?php
/**
 * Routes file for Wallet Operator related API
 */

/**
 * Create new wallet operator
 */
Route::post('/api/v1/wallet-operator/new', function()
{
    return WalletOperatorAPIController::create()->postNewWalletOperator();
});