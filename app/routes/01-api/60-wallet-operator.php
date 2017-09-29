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

/**
 * Create update wallet operator
 */
Route::post('/api/v1/wallet-operator/update', function()
{
    return WalletOperatorAPIController::create()->postUpdateWalletOperator();
});

/**
 * Get search wallet operator
 */
Route::get('/api/v1/wallet-operator/list', function()
{
    return WalletOperatorAPIController::create()->getSearchWalletOperator();
});

/**
 * Get search bank
 */
Route::get('/api/v1/bank/list', function()
{
    return WalletOperatorAPIController::create()->getSearchBank();
});