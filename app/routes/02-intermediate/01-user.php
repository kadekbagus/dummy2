<?php
/**
 * Routes file for Intermediate User API
 */

// PMP Account List
Route::get('/app/v1/account/list', 'IntermediateAuthController@Account_getAccount');

// Create new PMP Account
Route::post('/app/v1/account/new', 'IntermediateAuthController@Account_postNewAccount');

// Update a PMP Account
Route::post('/app/v1/account/update', 'IntermediateAuthController@Account_postUpdateAccount');

// ...
Route::get('/app/v1/account/tenants/available', 'IntermediateAuthController@Account_getAvailableTenantsSelection');

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
 * List membership
 */
Route::get('/app/v1/membership/list', 'IntermediateAuthController@User_getConsumerListing');

/**
 * Update New Membership
 */
Route::post('/app/v1/membership/update', 'IntermediateAuthController@User_postUpdateMembership');

/**
 * Delete Membership
 */
Route::post('/app/v1/membership/delete', 'IntermediateAuthController@User_postDeleteMembership');

/**
 * User Report Listing
 */
Route::get('/app/v1/user-report/list', 'IntermediateAuthController@UserReport_getUserReport');

/**
 * Create New PMP Employee
 */
Route::post('/app/v1/pmp-employee/new', 'IntermediateAuthController@Employee_postNewPMPEmployee');

/**
 * Update PMP Employee
 */
Route::post('/app/v1/pmp-employee/update', 'IntermediateAuthController@Employee_postUpdatePMPEmployee');

/**
 * Search PMP Employees
 */
Route::get('/app/v1/pmp-employee/list', 'IntermediateAuthController@Employee_getSearchPMPEmployee');

/**
 * Get list of user reward
 */
Route::get('/app/v1/pub/user-reward', ['as' => 'pub-user-reward', 'uses' => 'IntermediatePubAuthController@UserReward_getUserReward']);

/**
 * Get user profile.
 */
Route::get('/app/v1/pub/user-profile', ['as' => 'pub-user-profile', 'uses' => 'IntermediatePubAuthController@UserProfile\Profile_getUserProfile']);

/**
 * Get user photos.
 */
Route::get('/app/v1/pub/user-profile/photos', ['as' => 'pub-user-photos', 'uses' => 'IntermediatePubAuthController@UserProfile\Photos_getUserPhotos']);

/**
 * Get list of user review list
 */
Route::get('/app/v1/pub/user-profile/review', ['as' => 'pub-user-profile-review', 'uses' => 'IntermediatePubAuthController@UserProfile\ProfileReviewList_getReviewList']);

/**
 * Get leaderboard data.
 */
Route::get('/app/v1/pub/leaderboard', ['as' => 'pub-leaderboard', 'uses' => 'IntermediatePubAuthController@Leaderboard_getLeaderboardData']);
