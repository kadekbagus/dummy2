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
 * List/Search advert location
 */
Route::get('/api/v1/advert-location/{search}', function()
{
    return AdvertLocationAPIController::create()->getAdvertLocations();
})->where('search', '(list|search)');

/**
 * Get pub banner advert
 */
Route::get('/api/v1/pub/advert/{search}', function()
{
    return Orbit\Controller\API\v1\Pub\Advert\AdvertBannerListAPIController::create()->getAdvertBannerList();
})->where('search', '(search|list)');

Route::get('/app/v1/pub/advert/{search}', [
    'as' => 'pub-advert-list',
    'uses' => 'IntermediatePubAuthController@Advert\AdvertBannerList_getAdvertBannerList'
])->where('search', '(search|list)');

/**
 * List/Search advert city
 */
Route::get('/api/v1/advert-city/{search}', function()
{
    return AdvertLocationAPIController::create()->getAdvertCities();
})->where('search', '(list|search)');

/**
 * List/Search featured location
 */
Route::get('/api/v1/featured-mall/{search}', function()
{
    return FeaturedLocationMallAPIController::create()->getFeaturedLocationMall();
})->where('search', '(list|search)');

/**
 * List/Search featured advert
 */
Route::get('/api/v1/featured/{search}', function()
{
    return FeaturedAdvertAPIController::create()->getFeaturedList();
})->where('search', '(list|search)');

/**
 * List/Search featured advert country
 */
Route::get('/api/v1/featured-country/{search}', function()
{
    return FeaturedCountryAPIController::create()->getFeaturedCountry();
})->where('search', '(list|search)');

/**
 * List/Search featured advert city
 */
Route::get('/api/v1/featured-city/{search}', function()
{
    return FeaturedCityAPIController::create()->getFeaturedCity();
})->where('search', '(list|search)');

/**
 * Create featured advert slot
 */
Route::post('/api/v1/featured-slot/new', function()
{
    return FeaturedSlotNewAPIController::create()->postNewFeaturedSlot();
});


/**
 * List/Search featured advert slot
 */
Route::get('/api/v1/featured-slot/{search}', function()
{
    return FeaturedSlotListAPIController::create()->getListFeaturedSlot();
})->where('search', '(list|search)');