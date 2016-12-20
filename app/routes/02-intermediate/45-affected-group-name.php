<?php
/**
 * Routes file for Intermediate Affected Group Name API
 */

/**
 * Get search affected group name
 */
Route::get('/app/v1/affected-group-name/list', 'IntermediateAuthController@AffectedGroupName_getSearchAffectedGroupName');