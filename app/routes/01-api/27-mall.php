<?php
/**
 * Routes file for Mall related API
 */

/**
 * Create new mall
 */
Route::post('/api/v1/mall/new', function()
{
    return MallAPIController::create()->postNewMall();
});

/**
 * Delete mall
 */
Route::post('/api/v1/mall/delete', function()
{
    return MallAPIController::create()->postDeleteMall();
});

/**
 * Update mall
 */
Route::post('/api/v1/mall/update', function()
{
    return MallAPIController::create()->postUpdateMall();
});

/**
 * List/Search tenant
 */
Route::get('/api/v1/mall/{search}', function()
{
    return MallAPIController::create()->getSearchMall();
})->where('search', '(list|search)');

?>