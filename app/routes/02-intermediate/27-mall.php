<?php
/**
 * Routes file for Intermediate Mall API
 */

/**
 * Create new mall
 */
Route::post('/app/v1/mall/new', ['before' => 'orbit-settings', 'uses' => 'IntermediateAuthController@Mall_postNewMall']);

/**
 * Delete mall
 */
Route::post('/app/v1/mall/delete', ['before' => 'orbit-settings', 'uses' => 'IntermediateAuthController@Mall_postDeleteMall']);


/**
 * Update mall
 */
Route::post('/app/v1/mall/update', 'IntermediateAuthController@Mall_postUpdateMall');

/**
 * List and/or Search tenant
 */
Route::get('/app/v1/mall/{search}', 'IntermediateAuthController@Mall_getSearchMall')->where('search', '(list|search)');


/**
 * Tenant city list
 */
Route::get('/app/v1/mall/city', 'IntermediateAuthController@Tenant_getCityList');

?>