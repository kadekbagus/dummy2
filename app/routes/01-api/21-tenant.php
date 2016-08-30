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
 * List of tenant for campaign
 */
Route::get('/api/v1/tenant/campaignlocation', function()
{
    return TenantAPIController::create()->getCampaignLocation();
});

/**
 * Tenant city list
 */
Route::get('/api/v1/tenant/city', function()
{
    return TenantAPIController::create()->getCityList();
});

/**
 * Upload Retailer/Tenant logo
 */
Route::post('/api/v1/tenant-logo/upload', function()
{
    return UploadAPIController::create()->postUploadTenantLogo();
});

/**
 * Delete Retailer/Tenant logo
 */
Route::post('/api/v1/tenant-logo/delete', function()
{
    return UploadAPIController::create()->postDeleteTenantLogo();
});

/**
 * Upload Retailer/Tenant images
 */
Route::post('/api/v1/tenant-image/upload', function()
{
    return UploadAPIController::create()->postUploadTenantImage();
});

/**
 * Delete Retailer/Tenant images
 */
Route::post('/api/v1/tenant-image/delete', function()
{
    return UploadAPIController::create()->postDeleteTenantImage();
});

/**
 * Upload Retailer/Tenant map
 */
Route::post('/api/v1/tenant-map/upload', function()
{
    return UploadAPIController::create()->postUploadTenantMap();
});

/**
 * Delete Retailer/Tenant map
 */
Route::post('/api/v1/tenant-map/delete', function()
{
    return UploadAPIController::create()->postDeleteTenantMap();
});

/**
 * Upload Retailer/Tenant background
 */
Route::post('/api/v1/mall-background/upload', function()
{
    return UploadAPIController::create()->postUploadMallBackground();
});

/**
 * Delete Retailer/Tenant background
 */
Route::post('/api/v1/mall-background/delete', function()
{
    return UploadAPIController::create()->postDeleteMallBackground();
});

/**
 * Get Store list for gotomalls landing page
 */
Route::get('/api/v1/pub/store-list', function()
{
    return Orbit\Controller\API\v1\Pub\StoreAPIController::create()->getStoreList();
});

Route::get('/app/v1/pub/store-list', ['as' => 'pub-store-list', 'uses' => 'IntermediatePubAuthController@Store_getStoreList']);

/**
 * Get mall list based on store name
 */
Route::get('/api/v1/pub/mall-store-list', function()
{
    return Orbit\Controller\API\v1\Pub\StoreAPIController::create()->getMallStoreList();
});

Route::get('/app/v1/pub/mall-store-list', ['as' => 'pub-mall-store-list', 'uses' => 'IntermediatePubAuthController@Store_getMallStoreList']);

/**
 * Get Store Detail
 */
Route::get('/api/v1/pub/store-detail', function()
{
    return Orbit\Controller\API\v1\Pub\StoreAPIController::create()->getStoreDetail();
});

Route::get('/app/v1/pub/store-detail', ['as' => 'pub-store-detail', 'uses' => 'IntermediatePubAuthController@Store_getStoreDetail']);

/**
 * Get mall detail based on store name
 */
Route::get('/api/v1/pub/mall-detail-store', function()
{
    return Orbit\Controller\API\v1\Pub\StoreAPIController::create()->getMallDetailStore();
});

Route::get('/app/v1/pub/mall-detail-store', ['as' => 'pub-mall-detail-store', 'uses' => 'IntermediatePubAuthController@Store_getMallDetailStore']);
