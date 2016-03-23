<?php
/**
 * Routes file for Intermediate Tenant API
 */

/**
 * Create new tenant
 */
Route::post('/app/v1/tenant/new', ['uses' => 'IntermediateAuthController@Tenant_postNewTenant']);

/**
 * Delete tenant
 */
Route::post('/app/v1/tenant/delete', ['uses' => 'IntermediateAuthController@Tenant_postDeleteTenant']);

/**
 * Update tenant
 */
Route::post('/app/v1/tenant/update', ['uses' => 'IntermediateAuthController@Tenant_postUpdateTenant']);

/**
 * List and/or Search tenant
 */
Route::get('/app/v1/tenant/{search}', 'IntermediateAuthController@Tenant_getSearchTenant')
     ->where('search', '(list|search)');

/**
 * Tenant city list
 */
Route::get('/app/v1/tenant/city', 'IntermediateAuthController@Tenant_getCityList');

/**
 * List of tenant for campaign
 */
Route::get('/app/v1/tenant/campaignlocation', 'IntermediateAuthController@Tenant_getCampaignLocation');


/**
 * Upload Merchant Logo
 */
Route::post('/app/v1/tenant-logo/upload', 'IntermediateAuthController@Upload_postUploadTenantLogo');

/**
 * Delete Tenant Logo
 */
Route::post('/app/v1/tenant-logo/delete', 'IntermediateAuthController@Upload_postDeleteTenantLogo');

/**
 * Upload Tenant Picture
 */
Route::post('/app/v1/tenant-image/upload', 'IntermediateAuthController@Upload_postUploadTenantImage');

/**
 * Delete Tenant Picture
 */
Route::post('/app/v1/tenant-image/delete', 'IntermediateAuthController@Upload_postDeleteTenantImage');

/**
 * Upload Tenant Map
 */
Route::post('/app/v1/tenant-map/upload', 'IntermediateAuthController@Upload_postUploadTenantMap');

/**
 * Delete Tenant Map
 */
Route::post('/app/v1/tenant-map/delete', 'IntermediateAuthController@Upload_postDeleteTenantMap');

/**
 * Upload Tenant Background
 */
Route::post('/app/v1/mall-background/upload', 'IntermediateAuthController@Upload_postUploadMallBackground');

/**
 * Delete Tenant Background
 */
Route::post('/app/v1/mall-background/delete', 'IntermediateAuthController@Upload_postDeleteMallBackground');
