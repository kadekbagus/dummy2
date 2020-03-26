<?php

/**
 * Brand Product List API
 */
Route::get(
    '/app/v1/pub/brand-product-list',
    [
        'as' => 'brand-product-list',
        'uses' => 'IntermediatePubAuthController@BrandProduct\BrandProductList_handle'
    ]
);

/**
 * Brand Product Detail API
 */
Route::get(
    '/app/v1/pub/brand-product-detail',
    [
        'as' => 'brand-product-detail',
        'uses' => 'IntermediatePubAuthController@BrandProduct\BrandProductDetail_handle'
    ]
);

/**
 * Brand Product Reservation API
 */
Route::get(
    '/app/v1/pub/brand-product-reserve',
    [
        'as' => 'brand-product-reservation',
        'uses' => 'IntermediatePubAuthController@BrandProduct\BrandProductReservation_handle'
    ]
);

/**
 * Variant List
 */
Route::get(
    '/app/v1/brand-product-variant/list',
    [
        'as' => 'brand-product-variant-list',
        'uses' => 'IntermediateBrandProductAuthController@Variant\VariantList_handle',
    ]
);

/**
 * Brand Product Store list
 */
Route::get(
    '/app/v1/brand-product-store/list',
    [
        'as' => 'brand-product-store-list',
        'uses' => 'IntermediateBrandProductAuthController@Store\StoreList_handle',
    ]
);