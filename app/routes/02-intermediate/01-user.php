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

Route::group(['before' => 'orbit-settings'], function()
{
    /**
     * Create New Employee
     */
    Route::post('/app/v1/employee/new', 'IntermediateAuthController@Employee_postNewMallEmployee');

    /**
     * Update an Employee
     */
    Route::post('/app/v1/employee/update', 'IntermediateAuthController@Employee_postUpdateMallEmployee');

    /**
     * Delete an Employee
     */
    Route::post('/app/v1/employee/delete', 'IntermediateAuthController@Employee_postDeleteMallEmployee');

    /**
     * Search Employees
     */
    Route::get('/app/v1/employee/list', 'IntermediateAuthController@Employee_getSearchMallEmployee');

    /**
     * Create New Membership
     */
    Route::post('/app/v1/membership/new', 'IntermediateAuthController@User_postNewMembership');

    /**
     * Update New Membership
     */
    Route::post('/app/v1/membership/update', 'IntermediateAuthController@User_postUpdateMembership');

    /**
     * Delete Membership
     */
    Route::post('/app/v1/membership/delete', 'IntermediateAuthController@User_postDeleteMembership');
});
