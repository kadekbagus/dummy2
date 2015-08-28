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
 * Get active user statistics (from unique sign-ins) for several period
 */
Route::get('/api/v1/activity/active-user-statistics', function()
{
    return ActivityAPIController::create()->getActiveUserStatistics();
});
