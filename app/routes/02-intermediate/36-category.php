<?php
/**
 * Routes file for Intermediate Category API
 */

/**
 * Create new category
 */
Route::post('/app/v1/category/new', ['uses' => 'IntermediateAuthController@Category_postNewCategory']);

/**
 * Delete category
 */
Route::post('/app/v1/category/delete', ['uses' => 'IntermediateAuthController@Category_postDeleteCategory']);


/**
 * Update category
 */
Route::post('/app/v1/category/update', 'IntermediateAuthController@Category_postUpdateCategory');

/**
 * List and/or Search tenant
 */
Route::get('/app/v1/category/{search}', 'IntermediateAuthController@Category_getSearchCategory')->where('search', '(list|search)');

?>