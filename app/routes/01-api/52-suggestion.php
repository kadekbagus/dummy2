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