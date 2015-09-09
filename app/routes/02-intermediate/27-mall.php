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

?>