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

Route::post('/api/v1/marketplace/new', function()
{
    return Orbit\Controller\API\v1\Product\MarketplaceNewAPIController::create()->postNewMarketPlace();
});
Route::post('/app/v1/marketplace/new', ['as' => 'product-api-marketplace-new', 'uses' => 'IntermediateProductAuthController@MarketplaceNew_postNewMarketPlace']);


Route::post('/api/v1/marketplace/update', function()
{
    return Orbit\Controller\API\v1\Product\MarketplaceUpdateAPIController::create()->postUpdateMarketPlace();
});
Route::post('/app/v1/marketplace/update', ['as' => 'product-api-marketplace-update', 'uses' => 'IntermediateProductAuthController@MarketplaceUpdate_postUpdateMarketPlace']);

