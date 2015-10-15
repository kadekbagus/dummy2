<?php
/**
 * Routes file Customer Service membership related activity.
 */

Route::group(['before' => 'orbit-settings'], function()
{
    /**
     * Issue lucky draw to customer
     */
    Route::post('/api/v1/cs/membership/check-email', function()
    {
        return UserAPIController::create()->redirectToCloudGetID();
    });

    /**
     * Intermediate.
     */
    Route::post('/app/v1/cs/membership/check-email', 'IntermediateAuthController@User_redirectToCloudGetID');
});
