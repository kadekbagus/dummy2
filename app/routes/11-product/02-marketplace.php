<?php

/**
 * Marketplace related APIs
 */
Route::get('/api/v1/marketplace/{search}', function()
{
    return Orbit\Controller\API\v1\Product\MarketplaceListAPIController::create()->getSearchMarketplace();
})
->where('search', '(list|search)');

Route::get('/app/v1/marketplace/{search}', ['as' => 'product-api-marketplace-list', 'uses' => 'IntermediateProductAuthController@MarketplaceList_getSearchMarketplace'])
    ->where('search', '(list|search)');

Route::get('/api/v1/marketplace/detail', function()
{
    return Orbit\Controller\API\v1\Product\MarketplaceDetailAPIController::create()->getDetailMarketplace();
});

Route::get('/app/v1/marketplace/detail', ['as' => 'product-api-marketplace-detail', 'uses' => 'IntermediateProductAuthController@MarketplaceDetail_getDetailMarketplace']);


