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

Route::get('/app/v1/new-product/detail', ['as' => 'product-api-product-detail', 'uses' => 'IntermediateProductAuthController@ProductDetail_getDetailProduct']);


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


Route::post('/api/v1/new-product/mass-new', function()
{
    return Orbit\Controller\API\v1\Product\MassUpload\MassUploadAPIController::create()->postUploadProduct();
});
Route::post('/app/v1/new-product/mass-new', ['as' => 'product-api-product-new', 'uses' => 'IntermediateProductAuthController@MassUpload\MassUpload_postUploadProduct']);

/**
 * List/Search country
 */
Route::get('/api/v1/new-product/country/{search}', function()
{
    return Orbit\Controller\API\v1\Product\CountryListAPIController::create()->getSearchCountry();
})
->where('search', '(list|search)');

Route::get('/app/v1/new-product/country/{search}', ['as' => 'product-api-country-list', 'uses' => 'IntermediateProductAuthController@CountryList_getSearchCountry'])
    ->where('search', '(list|search)');


/**
 * List/Search category
 */
Route::get('/api/v1/new-product/category/{search}', function()
{
    return Orbit\Controller\API\v1\Product\CategoryListAPIController::create()->getSearchCategory();
})
->where('search', '(list|search)');

Route::get('/app/v1/new-product/category/{search}', ['as' => 'product-api-category-list', 'uses' => 'IntermediateProductAuthController@CategoryList_getSearchCategory'])
    ->where('search', '(list|search)');


/**
 * List/Search brand
 */
Route::get('/api/v1/new-product/brand/{search}', function()
{
    return Orbit\Controller\API\v1\Product\StoreListAPIController::create()->getSearchStore();
})
->where('search', '(list|search)');

Route::get('/app/v1/new-product/brand/{search}', ['as' => 'product-api-brand-list', 'uses' => 'IntermediateProductAuthController@StoreList_getSearchStore'])
    ->where('search', '(list|search)');



/**
 * List of product on brand detail page
 */
Route::get('/api/v1/pub/brand-product/{search}', function()
{
    return Orbit\Controller\API\v1\Pub\Product\ProductAffiliationListAPIController::create()->handle();
})->where('search', '(list|search)');

Route::get('/app/v1/pub/brand-product/{search}', ['as' => 'pub-brand-product-list', 'uses' => 'IntermediatePubAuthController@Product\ProductAffiliationList_handle'])
    ->where('search', '(list|search)');


/**
 * Detail product on brand detail page
 */
Route::get('/api/v1/pub/brand-product/detail', function()
{
    return Orbit\Controller\API\v1\Pub\Product\ProductDetailAPIController::create()->getDetailProduct();
});

Route::get('/app/v1/pub/brand-product/detail', ['as' => 'pub-brand-product-detail', 'uses' => 'IntermediatePubAuthController@Product\ProductDetail_getDetailProduct']);

/**
 * List of product affiliation.
 */
Route::get('/api/v1/pub/product-affiliation/{search}', function()
{
    return Orbit\Controller\API\v1\Pub\Product\ProductAffiliationListAPIController::create()->handle();
})->where('search', '(list|search)');

Route::get('/app/v1/pub/product-affiliation/{search}', ['as' => 'pub-product-affiliation-list', 'uses' => 'IntermediatePubAuthController@Product\ProductAffiliationList_handle'])
    ->where('search', '(list|search)');

/**
 * List of product affiliation.
 */
Route::get('/api/v1/pub/product-affiliation-suggestion/{search}', function()
{
    return Orbit\Controller\API\v1\Pub\Product\ProductAffiliationSuggestionListAPIController::create()->handle();
})->where('search', '(list|search)');

Route::get('/app/v1/pub/product-affiliation-suggestion/{search}', ['as' => 'pub-product-affiliation-list', 'uses' => 'IntermediatePubAuthController@Product\ProductAffiliationSuggestionList_handle'])
    ->where('search', '(list|search)');

/**
 * List of brand with product affiliation.
 */
Route::get('/api/v1/pub/brand-with-product-affiliation/{search}', function()
{
    return Orbit\Controller\API\v1\Pub\Product\BrandWithProductAffiliationListAPIController::create()->handle();
})->where('search', '(list|search)');

Route::get('/app/v1/pub/brand-with-product-affiliation/{search}', ['as' => 'pub-product-affiliation-list', 'uses' => 'IntermediatePubAuthController@Product\BrandWithProductAffiliationList_handle'])
    ->where('search', '(list|search)');


/**
 * Detail of product affiliation.
 */
Route::get('/api/v1/pub/product-affiliation/detail', function()
{
    return Orbit\Controller\API\v1\Pub\Product\ProductAffiliationDetailAPIController::create()->handle();
});

Route::get('/app/v1/pub/product-affiliation/detail', ['as' => 'pub-product-affiliation-detail', 'uses' => 'IntermediatePubAuthController@Product\ProductAffiliationDetail_handle']);
