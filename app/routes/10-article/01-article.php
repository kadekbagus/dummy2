<?php

/**
 * List/Search Article
 */

Route::get('/api/v1/article/{search}', function()
{
    return Orbit\Controller\API\v1\Article\ArticleListAPIController::create()->getSearchArticle();
})
->where('search', '(list|search)');

Route::get('/app/v1/article/{search}', ['as' => 'merchant-api-merchant-list', 'uses' => 'IntermediateMerchantAuthController@Merchant\MerchantList_getSearchMerchant'])
    ->where('search', '(list|search)');
