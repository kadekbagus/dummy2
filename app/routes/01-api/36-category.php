<?php
/**
 * Routes file for Category related API
 */

/**
 * Create new category
 */
Route::post('/api/v1/category/new', function()
{
    return CategoryAPIController::create()->postNewCategory();
});

/**
 * Delete category
 */
Route::post('/api/v1/category/delete', function()
{
    return CategoryAPIController::create()->postDeleteCategory();
});

/**
 * Update category
 */
Route::post('/api/v1/category/update', function()
{
    return CategoryAPIController::create()->postUpdateCategory();
});

/**
 * List/Search category
 */
Route::get('/api/v1/category/{search}', function()
{
    return CategoryAPIController::create()->getSearchCategory();
})->where('search', '(list|search)');


/**
 * Get Category list
 */
Route::get('/api/v1/pub/category-list', function()
{
    return Orbit\Controller\API\v1\Pub\CategoryAPIController::create()->getCategoryList();
});

Route::get('/app/v1/pub/category-list', ['as' => 'pub-category-list', 'uses' => 'IntermediatePubAuthController@Category_getCategoryList']);