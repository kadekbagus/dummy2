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
    return Orbit\Controller\API\v1\Pub\PromotionAPIController::create()->getSearchPromotion();
});

Route::get('/app/v1/pub/promotion-list', ['as' => 'pub-promotion-list', 'uses' => 'IntermediatePubAuthController@Promotion_getSearchPromotion']);

/**
 * List mall of promotion
 */
Route::get('/api/v1/pub/mall-promotion-list', function()
{
    return Orbit\Controller\API\v1\Pub\PromotionAPIController::create()->getMallPerPromotion();
});

Route::get('/app/v1/pub/mall-promotion-list', ['as' => 'pub-mall-promotion-list', 'uses' => 'IntermediatePubAuthController@Promotion_getMallPerPromotion']);


/**
 * Get mall promotion detail on landing page
 */
Route::get('/api/v1/pub/mall-promotion/detail', function()
{
    return Orbit\Controller\API\v1\Pub\PromotionAPIController::create()->getNewsItem();
});

Route::get('/app/v1/pub/mall-promotion/detail', ['as' => 'pub-mall-promotion-detail', 'uses' => 'IntermediatePubAuthController@Promotion_getPromotionItem']);

/**
 * List location of a news
 */
Route::get('/api/v1/pub/mall-promotion-location/list', function()
{
    return Orbit\Controller\API\v1\Pub\PromotionAPIController::create()->getMallPerNews();
});

Route::get('/app/v1/pub/mall-promotion-location/list', ['as' => 'pub-mall-promotion-location-list', 'uses' => 'IntermediatePubAuthController@Promotion_getPromotionLocations']);
