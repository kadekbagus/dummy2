<?php
/**
 * Routes file for Lucky Draw Number related API
 */

Route::group(['before' => 'orbit-settings'], function() {
    /**
     * Create new lucky draw number
     */
    Route::post('/api/v1/lucky-draw-number/new', function()
    {
        return LuckyDrawNumberAPIController::create()->postNewLuckyDrawNumber();
    });

    /**
     * Delete lucky draw number
     */
    Route::post('/api/v1/lucky-draw-number/delete', function()
    {
        return LuckyDrawNumberAPIController::create()->postDeleteLuckyDrawNumber();
    });

    /**
     * Update lucky draw number
     */
    Route::post('/api/v1/lucky-draw-number/update', function()
    {
        return LuckyDrawNumberAPIController::create()->postUpdateLuckyDrawNumber();
    });

    /**
     * List/Search lucky draw number
     */
    Route::get('/api/v1/lucky-draw-number/{search}', function()
    {
        return LuckyDrawNumberAPIController::create()->getSearchLuckyDrawNumber();
    })->where('search', '(list|search)');
});
