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
