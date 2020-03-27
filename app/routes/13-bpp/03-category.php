<?php

// Route for category list
Route::get('/app/v1/brand-product/category/list', ['as' => 'brand-category-list', 'uses' => 'IntermediateBrandProductAuthController@Category\CategoryList_getSearchCategory']);