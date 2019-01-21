<?php

//  ARTICLE MANAGER PORTAL

/**
 * List/Search Article
 */

Route::get('/api/v1/article/{search}', function()
{
    return Orbit\Controller\API\v1\Article\ArticleListAPIController::create()->getSearchArticle();
})
->where('search', '(list|search)');

Route::get('/app/v1/article/{search}', ['as' => 'article-api-article-list', 'uses' => 'IntermediateArticleAuthController@ArticleList_getSearchArticle'])
    ->where('search', '(list|search)');

/**
 * New article
 */
Route::post('/api/v1/article/new', function()
{
    return Orbit\Controller\API\v1\Article\ArticleNewAPIController::create()->postNewArticle();
});

Route::post('/app/v1/article/new', ['as' => 'article-api-article-new', 'uses' => 'IntermediateArticleAuthController@ArticleNew_postNewArticle']);

/**
 * Update article
 */
Route::post('/api/v1/article/update', function()
{
    return Orbit\Controller\API\v1\Article\ArticleUpdateAPIController::create()->postUpdateArticle();
});

Route::post('/app/v1/article/update', ['as' => 'article-api-article-update', 'uses' => 'IntermediateArticleAuthController@ArticleUpdate_postUpdateArticle']);

/**
 * List/Search country
 */
Route::get('/api/v1/article/country/{search}', function()
{
    return Orbit\Controller\API\v1\Merchant\CountryListAPIController::create()->getSearchCountry();
})
->where('search', '(list|search)');

Route::get('/app/v1/article/country/{search}', ['as' => 'article-api-country-list', 'uses' => 'IntermediateArticleAuthController@CountryList_getSearchCountry'])
    ->where('search', '(list|search)');

/**
 * List/Search cities
 */
Route::get('/api/v1/article/city/{search}', function()
{
    return Orbit\Controller\API\v1\Merchant\CityListAPIController::create()->getSearchCity();
})
->where('search', '(list|search)');

Route::get('/app/v1/article/city/{search}', ['as' => 'article-api-city-list', 'uses' => 'IntermediateArticleAuthController@CityList_getSearchCity'])
    ->where('search', '(list|search)');



// LANDING PAGE

/**
 * List of article
 */
Route::get('/api/v1/pub/article-list', function()
{
    return Orbit\Controller\API\v1\Pub\Article\ArticleListAPIController::create()->getSearchArticle();
});

Route::get('/app/v1/pub/article-list', ['as' => 'pub-article-list', 'uses' => 'IntermediatePubAuthController@Article\ArticleList_getSearchArticle']);

/**
 * Get article detail
 */
Route::get('/api/v1/pub/article/detail', function()
{
    return Orbit\Controller\API\v1\Pub\Article\ArticleDetailAPIController::create()->getArticleDetail();
});

Route::get('/app/v1/pub/article/detail', ['as' => 'pub-article-detail', 'uses' => 'IntermediatePubAuthController@Article\ArticleDetail_getArticleDetail']);
