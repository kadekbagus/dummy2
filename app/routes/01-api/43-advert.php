<?php
/**
 * Routes file for Advert related API
 */

/**
 * Create new advert
 */
Route::post('/api/v1/advert/new', function()
{
    return AdvertAPIController::create()->postNewAdvert();
});

/**
 * Delete advert
 */
Route::post('/api/v1/advert/delete', function()
{
    return AdvertAPIController::create()->postDeleteAdvert();
});

/**
 * Update advert
 */
Route::post('/api/v1/advert/update', function()
{
    return AdvertAPIController::create()->postUpdateAdvert();
});

/**
 * List/Search advert
 */
Route::get('/api/v1/advert/{search}', function()
{
    return AdvertAPIController::create()->getSearchAdvert();
})->where('search', '(list|search)');

/**
 * Upload advert image
 */
Route::post('/api/v1/advert-image/upload', function()
{
    return UploadAPIController::create()->postUploadAdvertImage();
});

/**
 * Delete advert image
 */
Route::post('/api/v1/advert-image/delete', function()
{
    return UploadAPIController::create()->postDeleteAdvertImage();
});

/**
 * List/Search advert placement
 */
Route::get('/api/v1/advert-placement/{search}', function()
{
    return AdvertPlacementAPIController::create()->getSearchAdvertPlacement();
})->where('search', '(list|search)');

/**
 * List/Search advert link
 */
Route::get('/api/v1/advert-link/{search}', function()
{
    return AdvertLinkAPIController::create()->getSearchAdvertLink();
})->where('search', '(list|search)');

/**
 * Get pub footer advert
 */
Route::get('/{app}/v1/pub/advert/{search}', [
    'as' => 'pub-advert-list',
    'uses' => 'IntermediatePubAuthController@Advert\AdvertList_getAdvertList'
])->where(['app' => '(api|app)', 'search' => '(search|list)']);

/**
 * List/Search advert location
 */
Route::get('/api/v1/advert-location/{search}', function()
{
    return AdvertLocationAPIController::create()->getAdvertLocations();
})->where('search', '(list|search)');