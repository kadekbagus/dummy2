<?php
/**
 * Routes file for Target Audience related API
 */

/**
 * Create target audience
 */
Route::post('/app/v1/target-audience/new', 'IntermediateAuthController@TargetAudience_postNewTargetAudience');

/**
 * Update target audience
 */
Route::post('/app/v1/target-audience/update', 'IntermediateAuthController@TargetAudience_postUpdateTargetAudience');

/**
 * Get search target audience
 */
Route::get('/app/v1/target-audience/list', 'IntermediateAuthController@TargetAudience_getSearchTargetAudience');