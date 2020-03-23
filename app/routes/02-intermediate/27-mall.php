<?php
/**
 * Routes file for Intermediate Mall API
 */

/**
 * Create new mall
 */
Route::post('/app/v1/mall/new', ['uses' => 'IntermediateAuthController@Mall_postNewMall']);

/**
 * Delete mall
 */
Route::post('/app/v1/mall/delete', ['uses' => 'IntermediateAuthController@Mall_postDeleteMall']);


/**
 * Update mall
 */
Route::post('/app/v1/mall/update', 'IntermediateAuthController@Mall_postUpdateMall');

/**
 * List and/or Search tenant
 */
Route::get('/app/v1/mall/{search}', 'IntermediateAuthController@Mall_getSearchMall')->where('search', '(list|search)');

/**
 * Detail mall
 */
Route::get('/app/v1/mall/detail', 'IntermediateAuthController@Mall_getMallDetail');

/**
 * List and/or Search Mall Name
 */
Route::get('/app/v1/mall-name/{search}', 'IntermediateAuthController@Mall_getSearchMallName')->where('search', '(list|search)');


/**
 * Tenant city list
 */
Route::get('/app/v1/mall/city', 'IntermediateAuthController@Mall_getCityList');

/**
 * Upload Mall Logo
 */
Route::post('/app/v1/mall-logo/upload', 'IntermediateAuthController@Upload_postUploadMallLogo');

/**
 * Delete Mall Logo
 */
Route::post('/app/v1/mall-logo/delete', 'IntermediateAuthController@Upload_postDeleteMallLogo');

/**
 * Upload Mall Map
 */
Route::post('/app/v1/mall-map/upload', 'IntermediateAuthController@Upload_postUploadMallMap');

/**
 * Delete Mall Map
 */
Route::post('/app/v1/mall-map/delete', 'IntermediateAuthController@Upload_postDeleteMallMap');

/**
 * List and/or Search mall base price
 */
Route::get('/app/v1/mall/baseprice', 'IntermediateAuthController@Mall_getMallBasePrice');
