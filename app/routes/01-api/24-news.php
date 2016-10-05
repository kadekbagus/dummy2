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
    return Orbit\Controller\API\v1\Pub\News\NewsListAPIController::create()->getSearchNews();
});

Route::get('/app/v1/pub/news-list', ['as' => 'pub-news-list', 'uses' => 'IntermediatePubAuthController@News\News_getSearchNews']);

/**
 * List mall of news
 */
Route::get('/api/v1/pub/mall-news-list', function()
{
    return Orbit\Controller\API\v1\Pub\News\NewsMallAPIController::create()->getMallPerNews();
});

Route::get('/app/v1/pub/mall-news-list', ['as' => 'pub-mall-news-list', 'uses' => 'IntermediatePubAuthController@News\News_getMallPerNews']);

/**
 * Get news detail on landing page
 */
Route::get('/api/v1/pub/news/detail', function()
{
    return Orbit\Controller\API\v1\Pub\News\NewsDetailAPIController::create()->getNewsItem();
});

Route::get('/app/v1/pub/news/detail', ['as' => 'pub-news-detail', 'uses' => 'IntermediatePubAuthController@News\News_getNewsItem']);

/**
 * List location of a news
 */
Route::get('/api/v1/pub/news-location/list', function()
{
    return Orbit\Controller\API\v1\Pub\News\NewsLocationAPIController::create()->getNewsLocations();
});

Route::get('/app/v1/pub/news-location/list', ['as' => 'pub-news-location-list', 'uses' => 'IntermediatePubAuthController@News\News_getNewsLocations']);
