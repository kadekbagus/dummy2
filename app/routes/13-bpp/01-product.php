<?php

// Route for product new
Route::post('/app/v1/brand-product/product/new', ['as' => 'brand-product-new', 'uses' => 'IntermediateBrandProductAuthController@Product\ProductNew_postNewProduct']);