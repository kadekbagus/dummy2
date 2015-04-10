<?php
/**
 * Routes file for Intermediate Widget API
 */

/**
 * Create New Widget
 */
Route::post('/app/v1/widget/new', 'IntermediateAuthController@Widget_postNewWidget');

/**
 * Update Widget
 */
Route::post('/app/v1/widget/update', 'IntermediateAuthController@Widget_postUpdateWidget');

/**
 * Delete Widget
 */
Route::post('/app/v1/widget/delete', 'IntermediateAuthController@Widget_postDeleteWidget');

/**
 * List Widgets
 */
Route::get('/app/v1/widget/list', 'IntermediateAuthController@Widget_getSearchWidget');
