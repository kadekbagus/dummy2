<?php
/**
 * Routes file for user related API
 */

/**
 * Create new user
 */
Route::post('/api/v1/user/new', function()
{
    return UserAPIController::create()->postNewUser();
});

/**
 * Delete user
 */
Route::post('/api/v1/user/delete', function()
{
    return UserAPIController::create()->postDeleteUser();
});

/**
 * Update user
 */
Route::post('/api/v1/user/update', function()
{
    return UserAPIController::create()->postUpdateUser();
});

/**
 * List/Search user
 */
Route::get('/api/v1/user/search', function()
{
    return UserAPIController::create()->getSearchUser();
});

/**
 * Delete user profile picture
 */
Route::get('/api/v1/user-profile-picture/delete', function()
{
    return UploadAPIController::create()->postUploadUserImage();
});

/**
 * Create New Employee
 */
Route::post('/api/v1/employee/new', function()
{
    return UserAPIController::create()->postNewEmployee();
});

/**
 * Update an Employee
 */
Route::post('/api/v1/employee/update', function()
{
    return UserAPIController::create()->postUpdateEmployee();
});

/**
 * Delete an Employee
 */
Route::post('/api/v1/employee/delete', function()
{
    return UserAPIController::create()->postDeleteEmployee();
});

/**
 * Search Employees
 */
Route::get('/api/v1/employee/list', function()
{
    return UserAPIController::create()->getSearchEmployee();
});

/**
 * Change password user
 */
Route::post('/api/v1/user/changepassword', ['as' => 'change-password', function()
{
    return UserAPIController::create()->postChangePassword();
}]);
