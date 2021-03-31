<?php

$brandProductAuth = 'IntermediateBrandProductAuthController';

// Route for product new
Route::post('/app/v1/brand-product/product/new', ['as' => 'brand-product-new', 'uses' => $brandProductAuth . '@Product\ProductNew_postNewProduct']);

// Route for product list
Route::get('/app/v1/brand-product/product/list', ['as' => 'brand-product-list', 'uses' => $brandProductAuth . '@Product\ProductList_getSearchProduct']);

// Route for product detail
Route::get('/app/v1/brand-product/product/detail', ['as' => 'brand-product-detail', 'uses' => 'IntermediateBrandProductAuthController@Product\ProductDetail_getProductDetail']);

// Route for brand product update
Route::post(
    '/app/v1/brand-product/product/update',
    [
        'as' => 'brand-product-update',
        'uses' => $brandProductAuth . '@Product\ProductUpdate_handle'
    ]
);

/**
 * Variant List
 */
Route::get(
    '/app/v1/brand-product-variant/list',
    [
        'as' => 'brand-product-variant-list',
        'uses' => $brandProductAuth . '@Variant\VariantList_handle',
    ]
);

/**
 * Brand Product Store list
 */
Route::get(
    '/app/v1/brand-product-store/list',
    [
        'as' => 'brand-product-store-list',
        'uses' => $brandProductAuth . '@Store\StoreList_handle',
    ]
);

/**
 * Brand Product Category list
 */
Route::get(
    '/app/v1/brand-product-category/list',
    [
        'as' => 'brand-product-category-list',
        'uses' => $brandProductAuth . '@Category\CategoryList_handle',
    ]
);

/**
 * Brand Product Update Status
 */
Route::post(
    '/app/v1/brand-product/product/update-status',
    [
        'as' => 'brand-product-update-status',
        'uses' => $brandProductAuth . '@Product\ProductUpdateStatus_handle',
    ]
);


// Route for user list
Route::get('/app/v1/brand-product/user/list', ['as' => 'brand-product-user-list', 'uses' => $brandProductAuth . '@User\BPPUserList_getSearchUser']);

// Route for store list for user creation
Route::get('/app/v1/brand-product/user/store/list', ['as' => 'brand-product-user-store-list', 'uses' => $brandProductAuth . '@User\BPPUserStoreList_getSearchStore']);

// Route for create user
Route::post('/app/v1/brand-product/user/new', ['as' => 'brand-product-user-new', 'uses' => $brandProductAuth . '@User\BPPUserNew_postNewUser']);

// Route for update user
Route::post('/app/v1/brand-product/user/update', ['as' => 'brand-product-user-update', 'uses' => $brandProductAuth . '@User\BPPUserUpdate_postUpdateUser']);

// Route for user detail
Route::get('/app/v1/brand-product/user/detail', ['as' => 'brand-product-user-detail', 'uses' => $brandProductAuth . '@User\BPPUserDetail_getDetail']);

// Route for marketplace list on bpp
Route::get('/app/v1/brand-product/marketplace/list', ['as' => 'brand-product-marketplace-list', 'uses' => $brandProductAuth . '@Marketplace\MarketplaceList_getSearchMarketplace']);