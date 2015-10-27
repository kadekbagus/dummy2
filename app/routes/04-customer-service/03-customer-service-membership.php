<?php
/**
 * Routes file Customer Service membership related activity.
 */

/**
 * Issue lucky draw to customer
 */
Route::get('/api/v1/cs/membership/check-email', function()
{
    return UserAPIController::create()->redirectToCloudGetID();
});

/**
 * Intermediate.
 */
Route::get('/app/v1/cs/membership/check-email', 'IntermediateAuthController@User_redirectToCloudGetID');
