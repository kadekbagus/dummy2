<?php
/**
 * Routes file for user personal interest API
 */

/**
 * Create new user
 */
Route::post('/api/v1/personal-interest/list', function()
{
    return PersonalInterestAPIController::create()->getSearchPersonalInterest();
});
