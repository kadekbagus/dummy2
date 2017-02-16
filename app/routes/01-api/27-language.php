<?php

// Get supported languages
Route::get('/api/v1/language/list', 'LanguageAPIController@getSearchLanguage');

// Active and inactive supported language
Route::get('/api/v1/language/update', 'LanguageAPIController@postUpdateSupportedLanguage');

// Get merchant languages
Route::get('/api/v1/language/list-merchant', 'LanguageAPIController@getSearchMerchantLanguage');

/**
 * Get Category list
 */
Route::get('/api/v1/pub/supportedlanguage', function()
{
    return Orbit\Controller\API\v1\Pub\SupportedLanguageAPIController::create()->getSupportedLanguageList();
});
Route::get('/app/v1/pub/supportedlanguage', ['as' => 'pub-supportedlanguage-list', 'uses' => 'IntermediatePubAuthController@SupportedLanguage_getSupportedLanguageList']);