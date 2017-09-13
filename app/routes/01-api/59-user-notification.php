<?php
/**
 * Routes file for user notification related API
 */

/**
 * Post new user notification
 */
Route::post('/api/v1/pub/user-notification/new', function()
{
    return Orbit\Controller\API\v1\Pub\UserNotification\UserNotificationNewAPIController::create()->postUserNotification();
});

Route::post('/app/v1/pub/user-notification/new', ['as' => 'user-notification-new', 'uses' => 'IntermediatePubAuthController@UserNotification\UserNotificationNew_postUserNotification']);