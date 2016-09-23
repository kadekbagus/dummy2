<?php
/**
 * Routes file for Mall related API
 */

/**
 * Create new mall
 */
Route::post('/api/v1/mall/new', function()
{
    return MallAPIController::create()->postNewMall();
});

/**
 * Delete mall
 */
Route::post('/api/v1/mall/delete', function()
{
    return MallAPIController::create()->postDeleteMall();
});

/**
 * Update mall
 */
Route::post('/api/v1/mall/update', function()
{
    return MallAPIController::create()->postUpdateMall();
});

/**
 * List/Search tenant
 */
Route::get('/api/v1/mall/{search}', function()
{
    return MallAPIController::create()->getSearchMall();
})->where('search', '(list|search)');


/**
 * Tenant city list
 */
Route::get('/api/v1/mall/city', function()
{
    return MallAPIController::create()->getCityList();
});

/**
 * Upload mall logo
 */
Route::post('/api/v1/mall-logo/upload', function()
{
    return UploadAPIController::create()->postUploadMallLogo();
});

/**
 * Delete mall logo
 */
Route::post('/api/v1/mall-logo/delete', function()
{
    return UploadAPIController::create()->postDeleteMallLogo();
});

/**
 * Get Mall geofence
 */
Route::get(
    '/{search}/v1/pub/mall-fence', ['as' => 'mall-fence', function()
    {
        return Orbit\Controller\API\v1\Pub\MallFenceAPIController::create()->getMallFence();
    }]
)->where('search', '(api|app)');

/**
 * Get Mall nearby
 */
Route::get(
    '/{search}/v1/pub/mall-nearby', ['as' => 'mall-nearby', function()
    {
        return Orbit\Controller\API\v1\Pub\MallNearbyAPIController::create()->getSearchMallNearby();
    }]
)->where('search', '(api|app)');

/**
 * Get Mall nearby
 */
Route::get(
    '/{search}/v1/pub/mall-nearby-es', ['as' => 'mall-nearby-es', function()
    {
        return Orbit\Controller\API\v1\Pub\MallNearbyAPIController::create()->getSearchMallKeyword();
    }]
)->where('search', '(api|app)');

/**
 * Get Mall in Map Area
 */
Route::get(
    '/{search}/v1/pub/mall-area', ['as' => 'mall-area', function()
    {
        return Orbit\Controller\API\v1\Pub\MallAreaAPIController::create()->getMallArea();
    }]
)->where('search', '(api|app)');

/**
 * Get Mall in Map Nearest
 */
Route::get(
    '/{search}/v1/pub/mall-nearest', ['as' => 'mall-nearest', function()
    {
        return Orbit\Controller\API\v1\Pub\MallNearestAPIController::create()->getSearchMallNearest();
    }]
)->where('search', '(api|app)');

/**
 * Get Mall list
 */
Route::get(
    '/{search}/v1/pub/mall-list', ['as' => 'mall-list', function()
    {
        return Orbit\Controller\API\v1\Pub\MallListAPIController::create()->getMallList();
    }]
)->where('search', '(api|app)');

/**
 * Get City list
 */
Route::get(
    '/{search}/v1/pub/mall-location-list', ['as' => 'mall-location-list', function()
    {
        return Orbit\Controller\API\v1\Pub\MallListAPIController::create()->getMallLocationList();
    }]
)->where('search', '(api|app)');
