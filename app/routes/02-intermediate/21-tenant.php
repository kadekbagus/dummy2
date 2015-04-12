<?php
/**
 * Routes file for Intermediate Tenant API
 */

/**
 * Create new tenant
 */
Route::post('/app/v1/tenant/new', 'IntermediateAuthController@Tenant_postNewTenant');

/**
 * Delete tenant
 */
Route::post('/app/v1/tenant/delete', 'IntermediateAuthController@Tenant_postDeleteTenant');

/**
 * Update tenant
 */
Route::post('/app/v1/tenant/update', 'IntermediateAuthController@Tenant_postUpdateTenant');

/**
 * List and/or Search tenant
 */
Route::get('/app/v1/tenant/{search}', 'IntermediateAuthController@Tenant_getSearchTenant')
     ->where('search', '(list|search)');

/**
 * Tenant city list
 */
Route::get('/app/v1/tenant/city', 'IntermediateAuthController@Tenant_getCityList');
