<?php
/**
 * Routes file for Intermediate Lucky Draw Number API
 */

Route::group(['before' => 'orbit-settings'], function() {

    /**
     * Create new lucky draw number
     */
    Route::post('/app/v1/lucky-draw-number/new', 'IntermediateAuthController@LuckyDrawNumber_postNewLuckyDrawNumber');

    /**
     * Delete lucky draw number
     */
    Route::post('/app/v1/lucky-draw-number/delete', 'IntermediateAuthController@LuckyDrawNumber_postDeleteLuckyDrawNumber');

    /**
     * Update lucky draw number
     */
    Route::post('/app/v1/lucky-draw-number/update', 'IntermediateAuthController@LuckyDrawNumber_postUpdateLuckyDrawNumber');

    /**
     * List and/or Search lucky draw number
     */
    Route::get('/app/v1/lucky-draw-number/{search}', 'IntermediateAuthController@LuckyDrawNumber_getSearchLuckyDrawNumber')
         ->where('search', '(list|search)');

});
