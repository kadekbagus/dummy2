<?php
/**
 * Routes file for POS Quick Product
 */

/**
 * Create New POS Quick Product
 */
Route::post('/api/v1/pos-quick-product/new', function()
{
    return PosQuickProduct::create()->postNewPosQuickProduct();
});

/**
 * Update POS Quick Product
 */
Route::post('/api/v1/pos-quick-product/update', function()
{
    return PosQuickProduct::create()->postUpdatePosQuickProduct();
});

/**
 * Delete POS Quick Product
 */
Route::post('/api/v1/pos-quick-product/delete', function()
{
    return PosQuickProduct::create()->postDeletePosQuickProduct();
});

/**
 * Get List POS Quick Product
 */
Route::get('/api/v1/pos-quick-product/list', function()
{
    return PosQuickProduct::create()->getSearchPosQuickProduct();
});
