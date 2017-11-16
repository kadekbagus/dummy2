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

/**
 * notification in Apps list
 */
Route::get('/api/v1/pub/user-notification/list', function()
{
    return Orbit\Controller\API\v1\Pub\UserNotification\UserNotificationListAPIController::create()->getUserNotificationList();
});

Route::get('/app/v1/pub/user-notification/list', ['as' => 'pub-user-notification-list', 'uses' => 'IntermediatePubAuthController@UserNotification\UserNotificationList_getUserNotificationList']);

/**
 * new notification counter
 */
Route::get('/api/v1/pub/user-notification/new', function()
{
    return Orbit\Controller\API\v1\Pub\UserNotification\UserNotificationListAPIController::create()->getUserNotificationNew();
});

Route::get('/app/v1/pub/user-notification/new', ['as' => 'pub-user-notification-new', 'uses' => 'IntermediatePubAuthController@UserNotification\UserNotificationList_getUserNotificationNew']);

/**
 * delete notification
 */
Route::post('/api/v1/pub/user-notification/delete', function()
{
    return Orbit\Controller\API\v1\Pub\UserNotification\UserNotificationDeleteAPIController::create()->postDeleteUserNotification();
});

Route::post('/app/v1/pub/user-notification/delete', ['as' => 'pub-user-notification-delete', 'uses' => 'IntermediatePubAuthController@UserNotification\UserNotificationDelete_postDeleteUserNotification']);

/**
 * set notification as read
 */
Route::post('/api/v1/pub/user-notification/read', function()
{
    return Orbit\Controller\API\v1\Pub\UserNotification\UserNotificationReadAPIController::create()->postUpdateUserNotificationAsRead();
});

Route::post('/app/v1/pub/user-notification/read', ['as' => 'pub-user-notification-read', 'uses' => 'IntermediatePubAuthController@UserNotification\UserNotificationRead_postUpdateUserNotificationAsRead']);