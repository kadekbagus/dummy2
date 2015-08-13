<?php
// Get global languages
Route::get('/api/v1/language/list', 'LanguageAPIController@getSearchLanguage');

// Get merchant languages
Route::get('/api/v1/language/list-merchant', 'LanguageAPIController@getSearchMerchantLanguage');

// Add merchant language
Route::get('/api/v1/language/add-merchant', 'LanguageAPIController@postAddMerchantLanguage');

// Delete merchant language
Route::get('/api/v1/language/delete-merchant', 'LanguageAPIController@postDeleteMerchantLanguage');
