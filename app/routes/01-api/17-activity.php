<?php
/**
 * Routes file for Activity related API
 */

/**
 * Get list of activities
 */
Route::get('/api/v1/activity/list', function()
{
    return ActivityAPIController::create()->getSearchActivity();
});

/**
 * Get sign up statistics for a period & compare to prev period
 */
Route::get('/api/v1/activity/sign-up-statistics', function()
{
    return ActivityAPIController::create()->getSignUpStatistics();
});

/**
 * Get device OS statistics (sign-in only) for a period
 */
Route::get('/api/v1/activity/device-os-statistics', function()
{
    return ActivityAPIController::create()->getDeviceOsStatistics();
});

/**
 * Get gender statistics (sign-in only) for a period
 */
Route::get('/api/v1/activity/gender-statistics', function()
{
    return ActivityAPIController::create()->getUserGenderStatistics();
});

/**
 * Get 'customer today' statistics for a period
 */
Route::get('/api/v1/activity/today-statistics', function()
{
    return ActivityAPIController::create()->getUserTodayStatistics();
});

/**
 * Get age statistics (sign-in only) for a period
 */
Route::get('/api/v1/activity/age-statistics', function()
{
    return ActivityAPIController::create()->getUserAgeStatistics();
});

/**
 * Get active user statistics (from unique sign-ins) for several period
 */
Route::get('/api/v1/activity/active-user-statistics', function()
{
    return ActivityAPIController::create()->getActiveUserStatistics();
});

/**
 * Get new (signups) vs returning (signins - signups) user statistics for several period
 */
Route::get('/api/v1/activity/new-returning-statistics', function()
{
    return ActivityAPIController::create()->getNewAndReturningUserStatistics();
});

/**
 * Get captive portal report
 */
Route::get('/api/v1/activity/captive-report', function()
{
    return ActivityAPIController::create()->getCaptivePortalReport();
});

/**
 * Get "connected now" user statistics for several period
 */
Route::get('/api/v1/activity/connected-now-statistics', function()
{
    return ActivityAPIController::create()->getConnectedNowStatistics();
});

/**
 * Get customer average connected time user for several period
 */
Route::get('/api/v1/activity/customer-average-connected-time', function()
{
    return ActivityAPIController::create()->getCustomerAverageConnectedTime();
});

/**
 * Get customer connected hourly user for several period
 */
Route::get('/api/v1/activity/customer-connected-hourly', function()
{
    return ActivityAPIController::create()->getCustomerConnectedHourly();
});

/**
 * Get CRM summary report
 */
Route::get('/api/v1/activity/crm-summary-report', function()
{
    return ActivityAPIController::create()->getCRMSummaryReport();
});

Route::get('/api/v1/activity/sections', function()
{
   return ActivityAPIController::create()->getSections(); 
});
