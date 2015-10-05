<?php

// Get supported languages
Route::get('/api/v1/language/list', 'LanguageAPIController@getSearchLanguage');

// Active and inactive supported language
Route::get('/api/v1/language/update', 'LanguageAPIController@postUpdateSupportedLanguage');

// Get merchant languages
Route::get('/api/v1/language/list-merchant', 'LanguageAPIController@getSearchMerchantLanguage');