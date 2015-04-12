<?php
/**
 * Routes file for Tenant related API
 */

/**
 * Create new tenant
 */
Route::post('/api/v1/tenant/new', function()
{
    return TenantAPIController::create()->postNewTenant();
});

/**
 * Delete tenant
 */
Route::post('/api/v1/tenant/delete', function()
{
    return TenantAPIController::create()->postDeleteTenant();
});

/**
 * Update tenant
 */
Route::post('/api/v1/tenant/update', function()
{
    return TenantAPIController::create()->postUpdateTenant();
});

/**
 * List/Search tenant
 */
Route::get('/api/v1/tenant/{search}', function()
{
    return TenantAPIController::create()->getSearchTenant();
})->where('search', '(list|search)');

/**
 * Tenant city list
 */
Route::get('/api/v1/tenant/city', function()
{
    return TenantAPIController::create()->getCityList();
});

/**
 * Upload Merchant logo
 */
Route::post('/api/v1/tenant-logo/upload', function()
{
    return UploadAPIController::create()->postUploadTenantLogo();
});

/**
 * Delete Merchant logo
 */
Route::post('/api/v1/tenant-logo/delete', function()
{
    return UploadAPIController::create()->postDeleteTenantLogo();
});

/**
 * Upload Merchant logo
 */
Route::post('/api/v1/tenant-image/upload', function()
{
    return UploadAPIController::create()->postUploadTenantImage();
});

/**
 * Delete Merchant logo
 */
Route::post('/api/v1/tenant-image/delete', function()
{
    return UploadAPIController::create()->postDeleteTenantImage();
});
