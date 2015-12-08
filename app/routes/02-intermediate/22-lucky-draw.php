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
Route::post('/app/v1/lucky-draw/delete', 'IntermediateAuthController@LuckyDraw_postDeleteLuckyDraw');

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

/**
 * List and/or Search lucky draw by mall
 */
Route::get('/app/v1/lucky-draw/by-mall/{search}', 'IntermediateAuthController@LuckyDraw_getSearchLuckyDrawByMall')
 	->where('search', '(list|search)');

/**
 * Create new lucky draw announcement
 */
Route::post('/app/v1/lucky-draw-announcement/new', 'IntermediateAuthController@LuckyDraw_postNewLuckyDrawAnnouncement');

/**
 * Update lucky draw announcement
 */
Route::post('/app/v1/lucky-draw-announcement/update', 'IntermediateAuthController@LuckyDraw_postUpdateLuckyDrawAnnouncement');

/**
 * List and/or Search lucky draw announcement
 */
Route::get('/app/v1/lucky-draw-announcement/{search}', 'IntermediateAuthController@LuckyDraw_getSearchLuckyDrawAnnouncement')
     ->where('search', '(list|search)');

/**
 * Create new lucky draw prize
 */
Route::post('/app/v1/lucky-draw-prize/new', 'IntermediateAuthController@LuckyDraw_postNewLuckyDrawPrize');

/**
 * Update lucky draw prize
 */
Route::post('/app/v1/lucky-draw-prize/update', 'IntermediateAuthController@LuckyDraw_postUpdateLuckyDrawPrize');

/**
 * List and/or Search lucky draw prize
 */
Route::get('/app/v1/lucky-draw-prize/{search}', 'IntermediateAuthController@LuckyDraw_getSearchLuckyDrawPrize')
     ->where('search', '(list|search)');

/**
 * Bulk Update lucky draw prize
 */
Route::post('/app/v1/lucky-draw-prize/bulk-update', 'IntermediateAuthController@LuckyDraw_postNewAndUpdateLuckyDrawPrize');