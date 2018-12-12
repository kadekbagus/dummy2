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


