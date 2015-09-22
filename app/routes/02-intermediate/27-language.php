<?php

// Get global languages
Route::get('/app/v1/language/list', 'IntermediateAuthController@Language_getSearchLanguage');

// Get merchant languages
Route::get('/app/v1/language/list-merchant', 'IntermediateAuthController@Language_getSearchMerchantLanguage');

// Add and modif merchant language
Route::post('/app/v1/language/merchant', 'IntermediateAuthController@Language_postAddMerchantLanguage');