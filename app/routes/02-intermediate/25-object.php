<?php
/**
 * Routes file for Intermediate Object API
 */

/**
 * Create new object
 */
Route::post('/app/v1/object/new', 'IntermediateAuthController@Object_postNewObject');

/**
 * Delete object
 */
Route::post('/app/v1/object/delete', 'IntermediateAuthController@Object_postDeleteObject');

/**
 * Update object
 */
Route::post('/app/v1/object/update', 'IntermediateAuthController@Object_postUpdateObject');

/**
 * List and/or Search object
 */
Route::get('/app/v1/object/{search}', 'IntermediateAuthController@Object_getSearchObject')
     ->where('search', '(list|search)');
