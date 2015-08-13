<?php
/**
 * Routes file for Intermediate Lucky Draw API
 */

/**
 * Create new lucky draw
 */
Route::post('/app/v1/lucky-draw/new', 'IntermediateAuthController@LuckyDraw_postNewLuckyDraw');

/**
 * Delete lucky draw
 */
Route::post('/app/v1/lucky-draw/delete', ['before' => 'orbit-settings', 'uses' => 'IntermediateAuthController@LuckyDraw_postDeleteLuckyDraw']);

/**
 * Update lucky draw
 */
Route::post('/app/v1/lucky-draw/update', 'IntermediateAuthController@LuckyDraw_postUpdateLuckyDraw');

/**
 * List and/or Search lucky draw
 */
Route::get('/app/v1/lucky-draw/{search}', 'IntermediateAuthController@LuckyDraw_getSearchLuckyDraw')
     ->where('search', '(list|search)');

/**
 * Upload lucky draw image
 */
Route::post('/app/v1/lucky-draw-image/upload', 'IntermediateAuthController@Upload_postUploadLuckyDrawImage');

/**
 * Delete lucky draw image
 */
Route::post('/app/v1/lucky-draw-image/delete', 'IntermediateAuthController@Upload_postDeleteLuckyDrawImage');
