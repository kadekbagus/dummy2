<?php

// Get supported languages
Route::get('/app/v1/language/list', 'IntermediateAuthController@Language_getSearchLanguage');

// Active and inactive supported language
Route::post('/app/v1/language/update', 'IntermediateAuthController@Language_postUpdateSupportedLanguage');

// Get merchant languages
Route::get('/app/v1/language/list-merchant', 'IntermediateAuthController@Language_getSearchMerchantLanguage');

// Get merchant languages
Route::get('/app/v1/language/list-pmp', 'IntermediateAuthController@Language_getSearchPMPLanguage');

// Add and modif merchant language
Route::post('/app/v1/language/merchant', 'IntermediateAuthController@Language_postAddMerchantLanguage');