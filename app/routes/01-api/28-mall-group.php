<?php
/**
 * Routes file for Mall related API
 */

/**
 * Create new mall
 */
Route::post('/api/v1/mallgroup/new', function()
{
    return MallGroupAPIController::create()->postNewMallGroup();
});

/**
 * Delete mall
 */
Route::post('/api/v1/mallgroup/delete', function()
{
    return MallGroupAPIController::create()->postDeleteMallGroup();
});

/**
 * Update mall
 */
Route::post('/api/v1/mallgroup/update', function()
{
    return MallGroupAPIController::create()->postUpdateMallGroup();
});

/**
 * List/Search tenant
 */
Route::get('/api/v1/mallgroup/{search}', function()
{
    return MallGroupAPIController::create()->getSearchMallGroup();
})->where('search', '(list|search)');

/**
 * Upload mall group logo
 */
Route::post('/api/v1/mallgroup-logo/upload', function()
{
    return UploadAPIController::create()->postUploadMallGroupLogo();
});

/**
 * Delete mall group logo
 */
Route::post('/api/v1/mallgroup-logo/delete', function()
{
    return UploadAPIController::create()->postDeleteMallGroupLogo();
});

