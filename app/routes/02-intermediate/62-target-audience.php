<?php
/**
 * Routes file for Target Audience related API
 */

/**
 * Create new target audience
 */
Route::post('/app/v1/target-audience/new', 'IntermediateAuthController@TargetAudience_postNewTargetAudience');

/**
 * Get search target audience
 */
Route::get('/app/v1/target-audience/list', 'IntermediateAuthController@TargetAudience_getSearchTargetAudience');