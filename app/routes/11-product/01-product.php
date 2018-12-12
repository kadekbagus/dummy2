<?php

/**
 * Product related APIs
 */
Route::get('/api/v1/new-product/{search}', function()
{
    return Orbit\Controller\API\v1\Product\ProductListAPIController::create()->getSearchProduct();
})
->where('search', '(list|search)');

Route::get('/app/v1/new-product/{search}', ['as' => 'product-api-product-list', 'uses' => 'IntermediateProductAuthController@ProductList_getSearchProduct'])
    ->where('search', '(list|search)');

Route::get('/api/v1/new-product/detail', function()
{
    return Orbit\Controller\API\v1\Product\ProductDetailAPIController::create()->getDetailProduct();
});

Route::get('/app/v1/new-product/{search}', ['as' => 'product-api-product-detail', 'uses' => 'IntermediateProductAuthController@ProductDetail_getDetailProduct']);


Route::post('/api/v1/new-product/new', function()
{
    return Orbit\Controller\API\v1\Product\ProductNewAPIController::create()->postNewProduct();
});
Route::post('/app/v1/new-product/new', ['as' => 'product-api-product-new', 'uses' => 'IntermediateProductAuthController@ProductNew_postNewProduct']);


Route::post('/api/v1/new-product/update', function()
{
    return Orbit\Controller\API\v1\Product\ProductUpdateAPIController::create()->postUpdateProduct();
});
Route::post('/app/v1/new-product/update', ['as' => 'product-api-product-update', 'uses' => 'IntermediateProductAuthController@ProductUpdate_postUpdateProduct']);


Route::post('/api/v1/marketplace/new', function()
{
    return Orbit\Controller\API\v1\Product\MarketplaceNewAPIController::create()->postNewMarketPlace();
});
Route::post('/app/v1/marketplace/new', ['as' => 'product-api-marketplace-new', 'uses' => 'IntermediateProductAuthController@MarketplaceUpdate_postNewMarketPlace']);


Route::post('/api/v1/marketplace/update', function()
{
    return Orbit\Controller\API\v1\Product\MarketplaceUpdateAPIController::create()->postUpdateMarketPlace();
});
Route::post('/app/v1/marketplace/update', ['as' => 'product-api-marketplace-update', 'uses' => 'IntermediateProductAuthController@MarketplaceUpdate_postUpdateMarketPlace']);


