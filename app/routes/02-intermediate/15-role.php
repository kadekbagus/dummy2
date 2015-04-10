<?php
/**
 * Intermediate routes for Role API
 */

/**
 * List of Role
 */
Route::get('/app/v1/role/list', 'IntermediateAuthController@Role_getSearchRole');
