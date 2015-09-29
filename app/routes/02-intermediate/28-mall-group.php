<?php
/**
 * Routes file for Intermediate Mall API
 */

/**
 * Create new mall
 */
Route::post('/app/v1/mallgroup/new', ['before' => 'orbit-settings', 'uses' => 'IntermediateAuthController@MallGroup_postNewMallGroup']);

/**
 * Delete mall
 */
Route::post('/app/v1/mallgroup/delete', ['before' => 'orbit-settings', 'uses' => 'IntermediateAuthController@MallGroup_postDeleteMallGroup']);


/**
 * Update mall
 */
Route::post('/app/v1/mallgroup/update', 'IntermediateAuthController@MallGroup_postUpdateMallGroup');

/**
 * List and/or Search tenant
 */
Route::get('/app/v1/mallgroup/{search}', 'IntermediateAuthController@MallGroup_getSearchMallGroup')->where('search', '(list|search)');

?>