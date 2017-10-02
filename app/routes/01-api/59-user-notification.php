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

/**
 * List/Search notification
 */
Route::get('/api/v1/notification/{search}', function()
{
    return NotificationListAPIController::create()->getNotificationList();
})->where('search', '(list|search)');

/**
 * post new notifications
 */
Route::post('/api/v1/notification/new', function()
{
    return NotificationNewAPIController::create()->postNewNotification();
});

/**
 * post update notifications
 */
Route::post('/api/v1/notification/update', function()
{
    return NotificationUpdateAPIController::create()->postUpdateNotification();
});

/**
 * notifications detail
 */
Route::post('/api/v1/notification/detail', function()
{
    return NotificationDetailAPIController::create()->getNotificationDetail();
});