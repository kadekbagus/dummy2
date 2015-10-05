<?php
/**
 * Routes file for Lucky Draw Number Receipt related API
 */

Route::group(['before' => 'orbit-settings'], function() {

    /**
     * List/Search lucky draw number receipt
     */
    Route::get('/api/v1/lucky-draw-number-receipt/{search}', function()
    {
        return LuckyDrawNumberReceiptAPIController::create()->getSearchLuckyDrawNumberReceipt();
    })->where('search', '(list|search)');

});
