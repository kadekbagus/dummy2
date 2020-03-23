<?php
// route for provider-product new/create
Route::post('/api/v1/provider-product/new', function()
{
    return Orbit\Controller\API\v1\Product\ProviderProduct\ProviderProductNewAPIController::create()->postNewProviderProduct();
});
Route::post('/app/v1/provider-product/new', ['as' => 'provider-product-api-new', 'uses' => 'IntermediateProductAuthController@ProviderProduct\ProviderProductNew_postNewProviderProduct']);

// route for provider-product listing
Route::get('/api/v1/provider-product/{search}', function()
{
    return Orbit\Controller\API\v1\Product\ProviderProduct\ProviderProductListAPIController::create()->getSearchProviderProduct();
})->where('search', '(list|search)');

Route::get('/app/v1/provider-product/{search}', ['as' => 'provider-product-api-new', 'uses' => 'IntermediateProductAuthController@ProviderProduct\ProviderProductList_getSearchProviderProduct'])->where('search', '(list|search)');


// route for provider-product detail
Route::get('/api/v1/provider-product/detail', function()
{
    return Orbit\Controller\API\v1\Product\ProviderProduct\ProviderProductDetailAPIController::create()->getDetailProviderProduct();
});

Route::get('/app/v1/provider-product/detail', ['as' => 'provider-product-api-detail', 'uses' => 'IntermediateProductAuthController@ProviderProduct\ProviderProductDetail_getDetailProviderProduct']);


// route for provider-product update
Route::post('/api/v1/provider-product/update', function()
{
    return Orbit\Controller\API\v1\Product\ProviderProduct\ProviderProductUpdateAPIController::create()->postUpdateProviderProduct();
});
Route::post('/app/v1/provider-product/update', ['as' => 'provider-product-api-update', 'uses' => 'IntermediateProductAuthController@ProviderProduct\ProviderProductUpdate_postUpdateProviderProduct']);