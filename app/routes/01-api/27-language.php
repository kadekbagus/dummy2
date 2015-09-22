<?php
// Get global languages
Route::get('/api/v1/language/list', 'LanguageAPIController@getSearchLanguage');

// Get merchant languages
Route::get('/api/v1/language/list-merchant', 'LanguageAPIController@getSearchMerchantLanguage');

// Add and modif merchant language
Route::get('/api/v1/language/merchant', 'LanguageAPIController@postAddMerchantLanguage');