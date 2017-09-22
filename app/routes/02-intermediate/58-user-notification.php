<?php
/**
 * Routes file for Intermediate user notification API
 */

/**
 * List of notification
 */
Route::get('/app/v1/notification-list/{search}', 'IntermediateAuthController@NotificationList_getNotificationList')
     ->where('search', '(list|search)');