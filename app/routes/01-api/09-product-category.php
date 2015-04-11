<?php
/**
 * Routes file for Product Category (Family) related API
 */

/**
 * Create new family
 */
Route::post('/api/v1/family/new', function()
{
    return CategoryAPIController::create()->postNewCategory();
});

/**
 * Delete family
 */
Route::post('/api/v1/family/delete', function()
{
    return CategoryAPIController::create()->postDeleteCategory();
});

/**
 * Update family
 */
Route::post('/api/v1/family/update', function()
{
    return CategoryAPIController::create()->postUpdateCategory();
});

/**
 * List/Search family
 */
Route::get('/api/v1/family/search', function()
{
    return CategoryAPIController::create()->getSearchCategory();
});
