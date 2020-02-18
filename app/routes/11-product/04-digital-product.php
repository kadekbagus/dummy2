<?php

// Route for Digital Product listing
Route::get('/app/v1/digital-product/{search}', ['as' => 'digital-product-list', 'uses' => 'IntermediateProductAuthController@DigitalProduct\DigitalProductList_getList'])->where('search', '(list|search)');

// Route for Digital Product new/create
Route::post('/app/v1/digital-product/new', ['as' => 'digital-product-new', 'uses' => 'IntermediateProductAuthController@DigitalProduct\DigitalProductNew_postNew']);

// route for Digital Product detail
Route::get('/app/v1/digital-product/detail', ['as' => 'digital-product-detail', 'uses' => 'IntermediateProductAuthController@DigitalProduct\DigitalProductDetail_getDetail']);

// route for Digital Product update
Route::post('/app/v1/digital-product/update', ['as' => 'digital-product-update', 'uses' => 'IntermediateProductAuthController@DigitalProduct\DigitalProductUpdate_postUpdate']);
