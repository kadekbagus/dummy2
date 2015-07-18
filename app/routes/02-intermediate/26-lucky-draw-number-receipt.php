<?php
/**
 * Routes file for Intermediate Lucky Draw Number Receipt API
 */

Route::group(['before' => 'orbit-settings'], function() {

    /**
     * List and/or Search lucky draw number receipt
     */
    Route::get('/app/v1/lucky-draw-number-receipt/{search}', 'IntermediateAuthController@LuckyDrawNumberReceipt_getSearchLuckyDrawNumberReceipt')
         ->where('search', '(list|search)');

});
