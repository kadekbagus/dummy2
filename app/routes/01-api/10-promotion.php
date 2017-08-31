<?php
/**
 * Routes file for Promotion related API
 */

/**
 * Create new promotion
 */
Route::post('/api/v1/promotion/new', function()
{
    return PromotionAPIController::create()->postNewPromotion();
});

/**
 * Delete promotion
 */
Route::post('/api/v1/promotion/delete', function()
{
    return PromotionAPIController::create()->postDeletePromotion();
});

/**
 * Update promotion
 */
Route::post('/api/v1/promotion/update', function()
{
    return PromotionAPIController::create()->postUpdatePromotion();
});

/**
 * List/Search promotion
 */
Route::get('/api/v1/promotion/search', function()
{
    return PromotionAPIController::create()->getSearchPromotion();
});

/**
 * List/Search promotion by retailer
 */
Route::get('/api/v1/promotion/by-retailer/search', function()
{
    return PromotionAPIController::create()->getSearchPromotionByRetailer();
});

/**
 * Upload promotion image
 */
Route::post('/api/v1/promotion/upload/image', function()
{
    return UploadAPIController::create()->postUploadPromotionImage();
});

/**
 * Delete promotion image
 */
Route::post('/api/v1/promotion/delete/image', function()
{
    return UploadAPIController::create()->postDeletePromotionImage();
});

/**
 * List of promotion on all malls
 */
Route::get('/api/v1/pub/promotion-list', function()
{
    return Orbit\Controller\API\v1\Pub\Promotion\PromotionListAPIController::create()->getSearchPromotion();
});

Route::get('/app/v1/pub/promotion-list', ['as' => 'pub-promotion-list', 'uses' => 'IntermediatePubAuthController@Promotion\PromotionList_getSearchPromotion']);

/**
 * List mall of promotion
 */
Route::get('/api/v1/pub/mall-promotion-list', function()
{
    return Orbit\Controller\API\v1\Pub\Promotion\PromotionMallAPIController::create()->getMallPerPromotion();
});

Route::get('/app/v1/pub/mall-promotion-list', ['as' => 'pub-mall-promotion-list', 'uses' => 'IntermediatePubAuthController@Promotion\PromotionMall_getMallPerPromotion']);


/**
 * Get mall promotion detail on landing page
 */
Route::get('/api/v1/pub/mall-promotion/detail', function()
{
    return Orbit\Controller\API\v1\Pub\Promotion\PromotionDetailAPIController::create()->getPromotionItem();
});

Route::get('/app/v1/pub/mall-promotion/detail', ['as' => 'pub-mall-promotion-detail', 'uses' => 'IntermediatePubAuthController@Promotion\PromotionDetail_getPromotionItem']);

/**
 * List location of a news
 */
Route::get('/api/v1/pub/mall-promotion-location/list', function()
{
    return Orbit\Controller\API\v1\Pub\Promotion\PromotionLocationAPIController::create()->getPromotionLocations();
});

Route::get('/app/v1/pub/mall-promotion-location/list', ['as' => 'pub-mall-promotion-location-list', 'uses' => 'IntermediatePubAuthController@Promotion\PromotionLocation_getPromotionLocations']);

/**
 * List store location in promotion detil
 */
Route::get('/api/v1/pub/store-promotion/list', function()
{
    return Orbit\Controller\API\v1\Pub\Promotion\PromotionStoreAPIController::create()->getPromotionStore();
});

Route::get('/app/v1/pub/store-promotion/list', ['as' => 'pub-store-promotion-list', 'uses' => 'IntermediatePubAuthController@Promotion\PromotionStore_getPromotionStore']);


/**
 * Get number of promotion location
 */
Route::get('/api/v1/pub/promotion-location/total', function()
{
    return Orbit\Controller\API\v1\Pub\Promotion\NumberOfPromotionLocationAPIController::create()->getNumberOfPromotionLocation();
});

Route::get('/app/v1/pub/promotion-location/total', ['as' => 'pub-promotion-location-total', 'uses' => 'IntermediatePubAuthController@Promotion\NumberOfPromotionLocation_getNumberOfPromotionLocation']);


/**
 * List city for promotion
 */
Route::get('/api/v1/pub/promotion-city/list', function()
{
    return Orbit\Controller\API\v1\Pub\Promotion\PromotionCityAPIController::create()->getPromotionCity();
});

Route::get('/app/v1/pub/promotion-city/list', ['as' => 'pub-promotion-city', 'uses' => 'IntermediatePubAuthController@Promotion\PromotionCity_getPromotionCity']);

/**
 * Also like List of promotion
 */
Route::get('/api/v1/pub/promotion/suggestion/list', function()
{
    return Orbit\Controller\API\v1\Pub\Promotion\PromotionAlsoLikeListAPIController::create()->getSearchPromotion();
});

Route::get('/app/v1/pub/promotion/suggestion/list', ['as' => 'pub-promotion-suggestion-list', 'uses' => 'IntermediatePubAuthController@Promotion\PromotionAlsoLikeList_getSearchPromotion']);

/**
 * List promotion location for rating form
 */
Route::get('/api/v1/pub/promotion/rating/location', function()
{
    return Orbit\Controller\API\v1\Pub\Promotion\PromotionRatingLocationAPIController::create()->getPromotionRatingLocation();
});

Route::get('/app/v1/pub/promotion/rating/location', ['as' => 'promotion-rating-location', 'uses' => 'IntermediatePubAuthController@Promotion\PromotionRatingLocation_getPromotionRatingLocation']);

/**
 * List featured advert in promotion
 */
Route::get('/api/v1/pub/promotion-featured/list', function()
{
    return Orbit\Controller\API\v1\Pub\Promotion\PromotionFeaturedListAPIController::create()->getSearchFeaturedPromotion();
});

Route::get('/app/v1/pub/promotion-featured/list', ['as' => 'pub-promotion-featured', 'uses' => 'IntermediatePubAuthController@Promotion\PromotionFeaturedList_getSearchFeaturedPromotion']);