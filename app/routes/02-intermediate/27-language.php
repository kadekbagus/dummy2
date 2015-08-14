<?php

// Get global languages
Route::get('/app/v1/language/list', 'IntermediateAuthController@Language_getSearchLanguage');

// Get merchant languages
Route::get('/app/v1/language/list-merchant', 'IntermediateAuthController@Language_getSearchMerchantLanguage');

// Add merchant language
Route::get('/app/v1/language/add-merchant', 'IntermediateAuthController@Language_postAddMerchantLanguage');

// Delete merchant language
Route::get('/app/v1/language/delete-merchant', 'IntermediateAuthController@Language_postDeleteMerchantLanguage');
