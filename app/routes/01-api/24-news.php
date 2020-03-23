<?php
/**
 * Routes file for News related API
 */

/**
 * Create new news
 */
Route::post('/api/v1/news/new', function()
{
    return NewsAPIController::create()->postNewNews();
});

/**
 * Delete news
 */
Route::post('/api/v1/news/delete', function()
{
    return NewsAPIController::create()->postDeleteNews();
});

/**
 * Update news
 */
Route::post('/api/v1/news/update', function()
{
    return NewsAPIController::create()->postUpdateNews();
});

/**
 * List/Search news
 */
Route::get('/api/v1/news/{search}', function()
{
    return NewsAPIController::create()->getSearchNews();
})->where('search', '(list|search)');

/**
 * Detail news
 */
Route::get('/api/v1/news/detail', function()
{
    return NewsAPIController::create()->getDetailNews();
});


/**
 * Upload news image
 */
Route::post('/api/v1/news-image/upload', function()
{
    return UploadAPIController::create()->postUploadNewsImage();
});

/**
 * Delete news image
 */
Route::post('/api/v1/news-image/delete', function()
{
    return UploadAPIController::create()->postDeleteNewsImage();
});

/**
 * List/Search promotion by retailer
 */
Route::get('/api/v1/newspromotion/by-retailer/search', function()
{
    return NewsAPIController::create()->getSearchNewsPromotionByRetailer();
});


/**
 * List of news on all malls
 */
Route::get('/api/v1/pub/news-list', function()
{
    return Orbit\Controller\API\v1\Pub\News\NewsListNewAPIController::create()->getSearchNews();
});

Route::get('/app/v1/pub/news-list', ['as' => 'pub-news-list', 'uses' => 'IntermediatePubAuthController@News\NewsListNew_getSearchNews']);

/**
 * List mall of news
 */
Route::get('/api/v1/pub/mall-news-list', function()
{
    return Orbit\Controller\API\v1\Pub\News\NewsMallAPIController::create()->getMallPerNews();
});

Route::get('/app/v1/pub/mall-news-list', ['as' => 'pub-mall-news-list', 'uses' => 'IntermediatePubAuthController@News\NewsMall_getMallPerNews']);

/**
 * Get news detail on landing page
 */
Route::get('/api/v1/pub/news/detail', function()
{
    return Orbit\Controller\API\v1\Pub\News\NewsDetailAPIController::create()->getNewsItem();
});

Route::get('/app/v1/pub/news/detail', ['as' => 'pub-news-detail', 'uses' => 'IntermediatePubAuthController@News\NewsDetail_getNewsItem']);

/**
 * List location of a news
 */
Route::get('/api/v1/pub/news-location/list', function()
{
    return Orbit\Controller\API\v1\Pub\News\NewsLocationAPIController::create()->getNewsLocations();
});

Route::get('/app/v1/pub/news-location/list', ['as' => 'pub-news-location-list', 'uses' => 'IntermediatePubAuthController@News\NewsLocation_getNewsLocations']);

/**
 * List store list of a news
 */
Route::get('/api/v1/pub/store-news/list', function()
{
    return Orbit\Controller\API\v1\Pub\News\NewsStoreAPIController::create()->getNewsStore();
});

Route::get('/app/v1/pub/store-news/list', ['as' => 'pub-store-news-list', 'uses' => 'IntermediatePubAuthController@News\NewsStore_getNewsStore']);

/**
 * Get number of news location
 */
Route::get('/api/v1/pub/news-location/total', function()
{
    return Orbit\Controller\API\v1\Pub\News\NumberOfNewsLocationAPIController::create()->getNumberOfNewsLocation();
});

Route::get('/app/v1/pub/news-location/total', ['as' => 'pub-news-location-total', 'uses' => 'IntermediatePubAuthController@News\NumberOfNewsLocation_getNumberOfNewsLocation']);

/**
 * List city for news
 */
Route::get('/api/v1/pub/news-city/list', function()
{
    return Orbit\Controller\API\v1\Pub\News\NewsCityAPIController::create()->getNewsCity();
});

Route::get('/app/v1/pub/news-city/list', ['as' => 'pub-news-city', 'uses' => 'IntermediatePubAuthController@News\NewsCity_getNewsCity']);

/**
 * Also like List of news
 */
Route::get('/api/v1/pub/news/suggestion/list', function()
{
    return Orbit\Controller\API\v1\Pub\News\NewsAlsoLikeListAPIController::create()->getSearchNews();
});

Route::get('/app/v1/pub/news/suggestion/list', ['as' => 'pub-news-suggestion-list', 'uses' => 'IntermediatePubAuthController@News\NewsAlsoLikeList_getSearchNews']);

/**
 * List news location for rating form
 */
Route::get('/api/v1/pub/news/rating/location', function()
{
    return Orbit\Controller\API\v1\Pub\News\NewsRatingLocationAPIController::create()->getNewsRatingLocation();
});

Route::get('/app/v1/pub/news/rating/location', ['as' => 'news-rating-location', 'uses' => 'IntermediatePubAuthController@News\NewsRatingLocation_getNewsRatingLocation']);

/**
 * List of featured news
 */
Route::get('/api/v1/pub/news-featured/list', function()
{
    return Orbit\Controller\API\v1\Pub\News\NewsFeaturedListAPIController::create()->getFeaturedNews();
});

Route::get('/app/v1/pub/news-featured/list', ['as' => 'pub-news-featured-list', 'uses' => 'IntermediatePubAuthController@News\NewsFeaturedList_getFeaturedNews']);


/**
 * List of news on all malls for Article Portal
 */
Route::get('/api/v1/suggestion-list', function()
{
    return SuggestionListAPIController::create()->getSuggestionList();
});

Route::get('/app/v1/suggestion-list', ['as' => 'suggestion-list', 'uses' => 'IntermediateAuthController@SuggestionList_getSuggestionList']);