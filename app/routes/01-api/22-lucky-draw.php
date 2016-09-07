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

/**
 * Create new lucky draw announcement
 */
Route::post('/api/v1/lucky-draw-announcement/new', function()
{
    return LuckyDrawAPIController::create()->postNewLuckyDrawAnnouncement();
});

/**
 * Update lucky draw announcement
 */
Route::post('/api/v1/lucky-draw-announcement/update', function()
{
    return LuckyDrawAPIController::create()->postUpdateLuckyDrawAnnouncement();
});

/**
 * List/Search lucky draw announcement
 */
Route::get('/api/v1/lucky-draw-announcement/{search}', function()
{
    return LuckyDrawAPIController::create()->getSearchLuckyDrawAnnouncement();
})->where('search', '(list|search)');

/**
 * Create new lucky draw prize
 */
Route::post('/api/v1/lucky-draw-prize/new', function()
{
    return LuckyDrawAPIController::create()->postNewLuckyDrawPrize();
});

/**
 * Update lucky draw prize
 */
Route::post('/api/v1/lucky-draw-prize/update', function()
{
    return LuckyDrawAPIController::create()->postUpdateLuckyDrawPrize();
});

/**
 * List/Search lucky draw prize
 */
Route::get('/api/v1/lucky-draw-prize/{search}', function()
{
    return LuckyDrawAPIController::create()->getSearchLuckyDrawPrize();
})->where('search', '(list|search)');

/**
 * Bulk Update lucky draw prize
 */
Route::post('/api/v1/lucky-draw-prize/bulk-update', function()
{
    return LuckyDrawAPIController::create()->postNewAndUpdateLuckyDrawPrize();
});

/**
 * Blast lucky draw winner announcement
 */
Route::post('/api/v1/lucky-draw-announcement/blast', function()
{
    return LuckyDrawAPIController::create()->postBlastLuckyDrawAnnouncement();
});

/**
 * Route for getting list of lucky draw on all malls
 */
Route::get('/api/v1/pub/lucky-draw/list', function()
{
    return Orbit\Controller\API\v1\Pub\LuckyDrawAPIController::create()->getSearchLuckyDraw();
});

Route::get('/app/v1/pub/lucky-draw/list', ['as' => 'pub-lucky-draw-list', 'uses' => 'IntermediatePubAuthController@LuckyDraw_getSearchLuckyDraw']);
