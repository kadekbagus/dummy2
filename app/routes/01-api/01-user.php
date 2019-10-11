<?php
/**
 * Routes file for user related API
 */

// PMP Account List
Route::get('/api/v1/account/list', function()
{
    return AccountAPIController::create()->getAccount();
});

// Create new PMP Account
Route::post('/api/v1/account/new', function()
{
    return AccountAPIController::create()->postNewAccount();
});

// Update a PMP Account
Route::post('/api/v1/account/update', function()
{
    return AccountAPIController::create()->postUpdateAccount();
});

Route::get('/api/v1/account/tenants/available', function()
{
    return AccountAPIController::create()->getAvailableTenantsSelection();
});

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
    return EmployeeAPIController::create()->postNewMallEmployee();
});

/**
 * Update an Employee
 */
Route::post('/api/v1/employee/update', function()
{
    return EmployeeAPIController::create()->postUpdateMallEmployee();
});

/**
 * Delete an Employee
 */
Route::post('/api/v1/employee/delete', function()
{
    return EmployeeAPIController::create()->postDeleteMallEmployee();
});

/**
 * Search Employees
 */
Route::get('/api/v1/employee/list', function()
{
    return EmployeeAPIController::create()->getSearchMallEmployee();
});

/**
 * Create New Membership
 */
Route::post('/api/v1/membership/new', function()
{
    return UserAPIController::create()->postNewMembership();
});

/**
 * Update Membership
 */
Route::post('/api/v1/membership/update', function()
{
    return UserAPIController::create()->postUpdateMembership();
});

/**
 * Delete Membership
 */
Route::post('/api/v1/membership/delete', function()
{
    return UserAPIController::create()->postDeleteMembership();
});

/**
 * Change password user
 */
Route::post('/api/v1/user/changepassword', ['as' => 'change-password', function()
{
    return UserAPIController::create()->postChangePassword();
}]);

/**
 * User Report Listing
 */
Route::get('/api/v1/user-report/list', function()
{
    return UserReportAPIController::create()->getUserReport();
});

/**
 * Create New PMP Employee
 */
Route::post('/api/v1/pmp-employee/new', function()
{
    return EmployeeAPIController::create()->postNewPMPEmployee();
});

/**
 * Update PMP Employee
 */
Route::post('/api/v1/pmp-employee/update', function()
{
    return EmployeeAPIController::create()->postUpdatePMPEmployee();
});

/**
 * Search PMP Employees
 */
Route::get('/api/v1/pmp-employee/list', function()
{
    return EmployeeAPIController::create()->getSearchPMPEmployee();
});

/**
 * Route for sending the reset password link to user email
 */
Route::post(
    '/{prefix}/v1/pub/user/reset-password-link', ['as' => 'pub-user-reset-password-link', function()
    {
        return Orbit\Controller\API\v1\Pub\ResetPasswordLinkAPIController::create()->postResetPasswordLink();
    }]
)->where('prefix', '(api|app)');

/**
 * Route for updating password which coming from reset password link request
 */
Route::post(
    '/{prefix}/v1/pub/user/reset-password', ['as' => 'pub-user-reset-password', function()
    {
        return Orbit\Controller\API\v1\Pub\ResetPasswordAPIController::create()->postResetPassword();
    }]
)->where('prefix', '(api|app)');

/**
 * Route for user activitation
 */
Route::post(
    '/{prefix}/v1/pub/user/activate-account', ['as' => 'pub-user-activate-account', function()
    {
        return Orbit\Controller\API\v1\Pub\ActivationAPIController::create()->postActivateAccount();
    }]
)->where('prefix', '(api|app)');

/**
 * Route for updating password which coming from reset password link request
 */
Route::get(
    '/{prefix}/v1/pub/user/check-token', ['as' => 'pub-user-check-token', function()
    {
        return Orbit\Controller\API\v1\Pub\ResetPasswordAPIController::create()->getCheckResetPasswordToken();
    }]
)->where('prefix', '(api|app)');


/**
 * Route for checking activation token already activate or not
 */
Route::get(
    '/{prefix}/v1/pub/user/check-token-activation', ['as' => 'pub-user-check-token-activation', function()
    {
        return Orbit\Controller\API\v1\Pub\ResetPasswordAPIController::create()->getCheckActivationToken();
    }]
)->where('prefix', '(api|app)');

/**
 * Route for checking activation token already activate or not
 */
Route::post(
    '/{prefix}/v1/pub/user/resend-activation-email', ['as' => 'pub-resend-activation-email', function()
    {
        return Orbit\Controller\API\v1\Pub\ActivationResendEmailAPIController::create()->postResendActivationLink();
    }]
)->where('prefix', '(api|app)');


/**
 * Route for list of user reward / promotion event
 */
Route::get('/api/v1/pub/user-reward', function()
{
    return Orbit\Controller\API\v1\Pub\UserRewardAPIController::create()->getUserReward();
});

/**
 * Route for list of user reward / promotion event
 */
Route::get('/api/v1/pub/user-profile/review', function()
{
    return Orbit\Controller\API\v1\Pub\UserProfile\ProfileReviewListAPIController::create()->getReviewList();
});

/**
 * Route for list of user reward / promotion event
 */
Route::get('/api/v1/pub/user-profile/follow', function()
{
    return Orbit\Controller\API\v1\Pub\UserProfile\ProfileFollowListAPIController::create()->getFollowList();
});

Route::get('/{api}/v1/rgp/check-session', ['as' => 'rgp-check-session', function() {
    return UserRgpAPIController::create()->validateSession();
}])->where('api', '(api|app)');