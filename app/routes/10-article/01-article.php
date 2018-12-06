<?php

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
