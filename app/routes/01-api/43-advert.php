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
 * Get pub footer advert
 */
Route::get('/{app}/v1/pub/advert/{search}', [
    'as' => 'pub-advert-list',
    'uses' => 'IntermediatePubAuthController@Advert\AdvertList_getAdvertList'
])->where(['app' => '(api|app)', 'search' => '(search|list)']);