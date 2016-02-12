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
Route::get('/app/v1/activity/today-statistics', 'IntermediateAuthController@Activity_getUserTodayStatistics');
Route::get('/app/v1/activity/age-statistics', 'IntermediateAuthController@Activity_getUserAgeStatistics');
Route::get('/app/v1/activity/active-user-statistics', 'IntermediateAuthController@Activity_getActiveUserStatistics');
Route::get('/app/v1/activity/new-returning-statistics', 'IntermediateAuthController@Activity_getNewAndReturningUserStatistics');
Route::get('/app/v1/activity/captive-report', 'IntermediateAuthController@Activity_getCaptivePortalReport');
Route::get('/app/v1/activity/connected-now-statistics', 'IntermediateAuthController@Activity_getConnectedNowStatistics');
Route::get('/app/v1/activity/customer-average-connected-time', 'IntermediateAuthController@Activity_getCustomerAverageConnectedTime');
Route::get('/app/v1/activity/customer-connected-hourly', 'IntermediateAuthController@Activity_getCustomerConnectedHourly');
Route::get('/app/v1/activity/crm-summary-report', 'IntermediateAuthController@Activity_getCRMSummaryReport');
Route::get('/app/v1/activity/modules', 'IntermediateAuthController@Activity_getModules');
