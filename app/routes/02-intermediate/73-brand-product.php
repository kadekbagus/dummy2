<?php

$intermediateController = 'IntermediatePubAuthController@BrandProduct';

/**
 * Brand Product List API
 */
Route::get(
    '/app/v1/pub/brand-product-list',
    [
        'as' => 'brand-product-list',
        'uses' => $intermediateController . '\BrandProductList_handle'
    ]
);

/**
 * Brand Product Detail API
 */
Route::get(
    '/app/v1/pub/brand-product-detail',
    [
        'as' => 'brand-product-detail',
        'uses' => $intermediateController . '\BrandProductDetail_handle'
    ]
);

/**
 * Brand Product Reservation API
 */
Route::get(
    '/app/v1/pub/brand-product-reserve',
    [
        'as' => 'brand-product-reservation',
        'uses' => $intermediateController . '\BrandProductReservation_handle'
    ]
);

/**
 * Brand Product Scan
 */
Route::post(
    '/app/v1/pub/brand-product-scan',
    [
        'as' => 'brand-product-scan',
        'uses' => $intermediateController . '\BrandProductScan_handle',
    ]
);

/**
 * Brand List which has products.
 */
Route::get(
    '/app/v1/pub/brand-with-product-list',
    [
        'as' => 'brand-with-product-list',
        'uses' => $intermediateController . '\BrandWithProductList_handle',
    ]
);
