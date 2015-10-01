<?php
/**
 * Intermediate routes for user Activities API
 */

/**
 * List Widgets
 */
Route::get('/app/v1/activity/list', 'IntermediateAuthController@Activity_getSearchActivity');
Route::get('/app/v1/activity/sign-up-statistics', 'IntermediateAuthController@Activity_getSignUpStatistics');
Route::get('/app/v1/activity/device-os-statistics', 'IntermediateAuthController@Activity_getDeviceOsStatistics');
Route::get('/app/v1/activity/gender-statistics', 'IntermediateAuthController@Activity_getUserGenderStatistics');
Route::get('/app/v1/activity/active-user-statistics', 'IntermediateAuthController@Activity_getActiveUserStatistics');
Route::get('/app/v1/activity/new-returning-statistics', 'IntermediateAuthController@Activity_getNewAndReturningUserStatistics');
Route::get('/app/v1/activity/captive-report', 'IntermediateAuthController@Activity_getCaptivePortalReport');
