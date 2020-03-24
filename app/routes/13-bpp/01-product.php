<?php

// Route for product new
Route::post('/app/v1/brand-product/product/new', ['as' => 'brand-product-new', 'uses' => 'IntermediateBrandProductAuthController@Product\ProductNew_postNewProduct']);

// Route for product list
Route::get('/app/v1/brand-product/product/list', ['as' => 'brand-product-list', 'uses' => 'IntermediateBrandProductAuthController@Product\ProductList_getSearchProduct']);
