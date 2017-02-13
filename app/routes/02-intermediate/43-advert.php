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

/**
 * List and/or advert placement
 */
Route::get('/app/v1/advert-placement/{search}', 'IntermediateAuthController@AdvertPlacement_getSearchAdvertPlacement')
     ->where('search', '(list|search)');

/**
 * List and/or advert link
 */
Route::get('/app/v1/advert-link/{search}', 'IntermediateAuthController@AdvertLink_getSearchAdvertLink')
     ->where('search', '(list|search)');

/**
 * List and/or advert location
 */
Route::get('/app/v1/advert-location/{search}', 'IntermediateAuthController@AdvertLocation_getAdvertLocations')
     ->where('search', '(list|search)');

/**
 * List and/or advert city
 */
Route::get('/app/v1/advert-city/{search}', 'IntermediateAuthController@AdvertLocation_getAdvertCities')
     ->where('search', '(list|search)');