<?php
/**
 * Routes file for Lucky Draw related API
 */

/**
 * Create new lucky draw
 */
Route::post('/api/v1/lucky-draw/new', function()
{
    return LuckyDrawAPIController::create()->postNewLuckyDraw();
});

/**
 * Delete lucky draw
 */
Route::post('/api/v1/lucky-draw/delete', function()
{
    return LuckyDrawAPIController::create()->postDeleteLuckyDraw();
});

/**
 * Update lucky draw
 */
Route::post('/api/v1/lucky-draw/update', function()
{
    return LuckyDrawAPIController::create()->postUpdateLuckyDraw();
});

/**
 * List/Search lucky draw
 */
Route::get('/api/v1/lucky-draw/{search}', function()
{
    return LuckyDrawAPIController::create()->getSearchLuckyDraw();
})->where('search', '(list|search)');

/**
 * Upload lucky draw image
 */
Route::post('/api/v1/lucky-draw-image/upload', function()
{
    return UploadAPIController::create()->postUploadLuckyDrawImage();
});

/**
 * Delete lucky draw image
 */
Route::post('/api/v1/lucky-draw-image/delete', function()
{
    return UploadAPIController::create()->postDeleteLuckyDrawImage();
});

/**
 * List/Search lucky draw by mall
 */
Route::get('/api/v1/lucky-draw/by-mall/{search}', function()
{
    return LuckyDrawAPIController::create()->getSearchLuckyDrawByMall();
})->where('search', '(list|search)');
