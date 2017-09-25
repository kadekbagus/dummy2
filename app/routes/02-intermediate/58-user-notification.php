<?php
/**
 * Routes file for Intermediate user notification API
 */

/**
 * List of notification
 */
Route::get('/app/v1/notification/{search}', 'IntermediateAuthController@NotificationList_getNotificationList')
     ->where('search', '(list|search)');

/**
 * Create new notification
 */
Route::post('/app/v1/notification/new', 'IntermediateAuthController@NotificationNew_postNewNotification');

/**
 * update notification
 */
Route::post('/app/v1/notification/update', 'IntermediateAuthController@NotificationUpdate_postUpdateNotification');