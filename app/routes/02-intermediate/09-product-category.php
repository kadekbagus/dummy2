<?php
/**
 * Routes file for Intermediate Product Category (Family) API
 */

/**
 * Create new family
 */
Route::post('/app/v1/family/new', 'IntermediateAuthController@Category_postNewCategory');

/**
 * Delete family
 */
Route::post('/app/v1/family/delete', 'IntermediateAuthController@Category_postDeleteCategory');

/**
 * Update family
 */
Route::post('/app/v1/family/update', 'IntermediateAuthController@Category_postUpdateCategory');

/**
 * List and/or Search family
 */
Route::get('/app/v1/family/search', 'IntermediateAuthController@Category_getSearchCategory');
