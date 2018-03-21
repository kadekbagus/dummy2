<?php
/**
 * Routes file for suggestion related API
 */

/**
 * Get Suggestion list
 */
Route::get('/api/v1/pub/suggestion-list', function()
{
    return Orbit\Controller\API\v1\Pub\SuggestionAPIController::create()->getSuggestionList();
});
Route::get('/app/v1/pub/suggestion-list', ['as' => 'pub-suggestion-list', 'uses' => 'IntermediatePubAuthController@Suggestion_getSuggestionList']);

Route::get('/api/v1/pub/suggestion-malllevel-list', function()
{
    return Orbit\Controller\API\v1\Pub\SuggestionMallLevelAPIController::create()->getSuggestionMallLevelList();
});
Route::get('/app/v1/pub/suggestion-malllevel-list', ['as' => 'pub-suggestion-malllevel-list', 'uses' => 'IntermediatePubAuthController@SuggestionMallLevel_getSuggestionMallLevelList']);