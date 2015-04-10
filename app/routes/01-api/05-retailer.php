<?php
/**
 * Routes file for Retailer related API
 */

/**
 * Create new retailer
 */
Route::post('/api/v1/retailer/new', function()
{
    return RetailerAPIController::create()->postNewRetailer();
});

/**
 * Delete retailer
 */
Route::post('/api/v1/retailer/delete', function()
{
    return RetailerAPIController::create()->postDeleteRetailer();
});

/**
 * Update retailer
 */
Route::post('/api/v1/retailer/update', function()
{
    return RetailerAPIController::create()->postUpdateRetailer();
});

/**
 * List/Search retailer
 */
Route::get('/api/v1/retailer/search', function()
{
    return RetailerAPIController::create()->getSearchRetailer();
});

/**
 * Retailer city list
 */
Route::get('/api/v1/retailer/city', function()
{
    return RetailerAPIController::create()->getCityList();
});