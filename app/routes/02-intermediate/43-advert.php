<?php
/**
 * Routes file for Intermediate Advert API
 */

/**
 * Create new advert
 */
Route::post('/app/v1/advert/new', 'IntermediateAuthController@Advert_postNewAdvert');

/**
 * Delete advert
 */
Route::post('/app/v1/advert/delete', 'IntermediateAuthController@Advert_postDeleteAdvert');

/**
 * Update advert
 */
Route::post('/app/v1/advert/update', 'IntermediateAuthController@Advert_postUpdateAdvert');

/**
 * List and/or Search advert
 */
Route::get('/app/v1/advert/{search}', 'IntermediateAuthController@Advert_getSearchAdvert')
     ->where('search', '(list|search)');

/**
 * Upload advert image
 */
Route::post('/app/v1/advert-image/upload', 'IntermediateAuthController@Upload_postUploadAdvertImage');

/**
 * Delete advert image
 */
Route::post('/app/v1/advert-image/delete', 'IntermediateAuthController@Upload_postDeleteAdvertImage');