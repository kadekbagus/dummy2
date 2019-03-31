<?php
/**
 * Routes file for Intermediate TelcoOperator API
 */

/**
 * Create new telco
 */
Route::post('/app/v1/telco/new', 'IntermediateAuthController@TelcoOperator_postNewTelcoOperator');

/**
 * Update telco
 */
Route::post('/app/v1/telco/update', 'IntermediateAuthController@TelcoOperator_postUpdateTelcoOperator');

/**
 * Get search telco
 */
Route::get('/app/v1/telco/list', 'IntermediateAuthController@TelcoOperator_getSearchTelcoOperator');

/**
 * Get detail telco
 */
Route::get('/app/v1/telco/detail', 'IntermediateAuthController@TelcoOperator_getDetailTelcoOperator');
