<?php
/**
 * Intermediate routes for user Activities API
 */

/**
 * List Widgets
 */
Route::get('/app/v1/activity/list', 'IntermediateAuthController@Activity_getSearchActivity');
