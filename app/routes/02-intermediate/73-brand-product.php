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
 * Brand Product Scan
 */
Route::post(
    '/app/v1/pub/brand-product-scan',
    [
        'as' => 'brand-product-scan',
        'uses' => 'IntermediatePubAuthController@BrandProduct\BrandProductScan_handle',
    ]
);
