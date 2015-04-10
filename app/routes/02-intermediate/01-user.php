<?php
/**
 * Routes file for Intermediate User API
 */

/**
 * Create new user
 */
Route::post('/app/v1/user/new', 'IntermediateAuthController@User_postNewUser');

/**
 * Delete user
 */
Route::post('/app/v1/user/delete', 'IntermediateAuthController@User_postDeleteUser');

/**
 * Update user
 */
Route::post('/app/v1/user/update', 'IntermediateAuthController@User_postUpdateUser');

/**
 * List and/or Search user
 */
Route::get('/app/v1/user/search', 'IntermediateAuthController@User_getSearchUser');

/**
 * Change password user
 */
Route::post('/app/v1/user/changepassword', 'IntermediateAuthController@User_postChangePassword');

/**
 * Delete User profile picture
 */
Route::post('/app/v1/user-profile-picture/delete', 'IntermediateAuthController@Upload_postDeleteUserImage');

/**
 * Create New Employee
 */
Route::post('/app/v1/employee/new', 'IntermediateAuthController@Employee_postNewEmployee');

/**
 * Update an Employee
 */
Route::post('/app/v1/employee/update', 'IntermediateAuthController@Employee_postUpdateEmployee');

/**
 * Delete an Employee
 */
Route::post('/app/v1/employee/delete', 'IntermediateAuthController@Employee_postDeleteEmployee');

/**
 * Search Employees
 */
Route::get('/app/v1/employee/list', 'IntermediateAuthController@Employee_getSearchEmployee');
