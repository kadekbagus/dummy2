<?php
/**
 * Routes file for Transaction related API
 */

/**
 * Get list of merchants for particular user which has transactions
 */
Route::get('/api/v1/consumer-transaction-history/merchant-list', function()
{
    return TransactionHistoryAPIController::create()->getMerchantList();
});

/**
 * Get list of retailers for particular user which has transactions
 */
Route::get('/api/v1/consumer-transaction-history/retailer-list', function()
{
    return TransactionHistoryAPIController::create()->getMerchantList();
});

/**
 * Get list of product for particular user which has transactions
 */
Route::get('/api/v1/consumer-transaction-history/product-list', function()
{
    return TransactionHistoryAPIController::create()->getProductList();
});
