<?php
/**
 * Routes file for Intermediate Pulsa API
 */

/**
 * Create new pulsa
 */
// Route::post('/app/v1/pulsa/new', 'IntermediateAuthController@Pulsa_postNewPulsa');

/**
 * Update pulsa
 */
// Route::post('/app/v1/pulsa/update', 'IntermediateAuthController@Pulsa_postUpdatePulsa');

/**
 * Update pulsa status
 */
Route::post('/app/v1/pulsa/update-status', 'IntermediateAuthController@Pulsa_postUpdatePulsaStatus');

/**
 * Get search pulsa
 */
// Route::get('/app/v1/pulsa/list', 'IntermediateAuthController@Pulsa_getSearchPulsa');

/**
 * Get detail pulsa
 */
// Route::get('/app/v1/pulsa/detail', 'IntermediateAuthController@Pulsa_getDetailPulsa');