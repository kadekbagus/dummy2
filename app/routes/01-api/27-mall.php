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
 * Detail mall
 */
Route::get('/api/v1/mall/detail', function()
{
    return MallAPIController::create()->getMallDetail();
});

/**
 * List/Search tenant
 */
Route::get('/api/v1/mall-name/{search}', function()
{
    return MallAPIController::create()->getSearchMallName();
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
 * Upload mall map
 */
Route::post('/api/v1/mall-map/upload', function()
{
    return UploadAPIController::create()->postUploadMallMap();
});

/**
 * Delete mall map
 */
Route::post('/api/v1/mall-map/delete', function()
{
    return UploadAPIController::create()->postDeleteMallMap();
});

/**
 * Get Mall geofence
 */
Route::get('/api/v1/pub/mall-fence', function()
{
    return Orbit\Controller\API\v1\Pub\Mall\MallFenceAPIController::create()->getMallFence();
});

Route::get('/app/v1/pub/mall-fence', ['as' => 'mall-fence', 'uses' => 'IntermediatePubAuthController@Mall\MallFence_getMallFence']);

/**
 * Get Mall nearby
 */
Route::get('/api/v1/pub/mall-nearby', function()
{
    return Orbit\Controller\API\v1\Pub\Mall\MallNearbyAPIController::create()->getSearchMallNearby();
});

Route::get('/app/v1/pub/mall-nearby', ['as' => 'mall-nearby', 'uses' => 'IntermediatePubAuthController@Mall\MallNearby_getSearchMallNearby']);

/**
 * Get Mall nearby
 */
Route::get('/api/v1/pub/mall-nearby-es', function()
{
    return Orbit\Controller\API\v1\Pub\Mall\MallNearbyAPIController::create()->getSearchMallKeyword();
});

Route::get('/app/v1/pub/mall-nearby-es', ['as' => 'mall-nearby-es', 'uses' => 'IntermediatePubAuthController@Mall\MallNearby_getSearchMallKeyword']);

/**
 * Get Mall in Map Area
 */
Route::get('/api/v1/pub/mall-area', function()
{
    return Orbit\Controller\API\v1\Pub\Mall\MallAreaAPIController::create()->getMallArea();
});

Route::get('/app/v1/pub/mall-area', ['as' => 'mall-area', 'uses' => 'IntermediatePubAuthController@Mall\MallArea_getMallArea']);

/**
 * Get Mall in Map Nearest
 */
Route::get('/api/v1/pub/mall-nearest', function()
{
    return Orbit\Controller\API\v1\Pub\Mall\MallNearestAPIController::create()->getSearchMallNearest();
});

Route::get('/app/v1/pub/mall-nearest', ['as' => 'mall-nearest', 'uses' => 'IntermediatePubAuthController@Mall\MallNearest_getSearchMallNearest']);

/**
 * Get Mall list
 */
Route::get('/api/v1/pub/mall-list', function()
{
    return Orbit\Controller\API\v1\Pub\Mall\MallListNewAPIController::create()->getMallList();
});

Route::get('/app/v1/pub/mall-list', ['as' => 'mall-list', 'uses' => 'IntermediatePubAuthController@Mall\MallListNew_getMallList']);

/**
 * Get City list
 */
Route::get('/api/v1/pub/mall-location-list', function()
{
    return Orbit\Controller\API\v1\Pub\Mall\MallListAPIController::create()->getMallLocationList();
});

Route::get('/app/v1/pub/mall-location-list', ['as' => 'mall-location-list', 'uses' => 'IntermediatePubAuthController@Mall\MallList_getMallLocationList']);

/**
 * Get Country list
 */
Route::get('/api/v1/pub/mall-country-list', function()
{
    return Orbit\Controller\API\v1\Pub\Mall\MallListAPIController::create()->getMallLocationList();
});

Route::get('/app/v1/pub/mall-country-list', ['as' => 'mall-country-list', 'uses' => 'IntermediatePubAuthController@Mall\MallList_getMallCountryList']);

/**
 * Get Mall Info
 */
Route::get('/api/v1/pub/mall-info', function()
{
    return Orbit\Controller\API\v1\Pub\Mall\MallInfoAPIController::create()->getMallInfo();
});

Route::get('/app/v1/pub/mall-info', ['as' => 'mall-info', 'uses' => 'IntermediatePubAuthController@Mall\MallInfo_getMallInfo']);
