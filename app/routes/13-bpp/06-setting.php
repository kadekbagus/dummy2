<?php

// Route for setting store list
Route::get('/app/v1/brand-product/setting/store-list', ['as' => 'brand-product-setting-store-list', 'uses' => 'IntermediateBrandProductAuthController@Setting\SettingStoreList_getSearchStore']);

// Route for setting update
Route::post('/app/v1/brand-product/setting/update', ['as' => 'brand-product-setting-update', 'uses' => 'IntermediateBrandProductAuthController@Setting\SettingStoreUpdate_postUpdate']);