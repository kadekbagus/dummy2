<?php
/**
 * Routes file for Intermediate TelcoOperator API
 */

/**
 * Create new telco
 */
Route::post('/app/v1/telco/new', 'IntermediateProductAuthController@Pulsa\TelcoOperatorNew_postNewTelcoOperator');

/**
 * Update telco
 */
Route::post('/app/v1/telco/update', 'IntermediateProductAuthController@Pulsa\TelcoOperatorUpdate_postUpdateTelcoOperator');

/**
 * Get search telco
 */
Route::get('/app/v1/telco/list', 'IntermediateProductAuthController@Pulsa\TelcoOperatorList_getList');

/**
 * Get detail telco
 */
Route::get('/app/v1/telco/detail', 'IntermediateProductAuthController@Pulsa\TelcoOperatorDetail_getDetail');
