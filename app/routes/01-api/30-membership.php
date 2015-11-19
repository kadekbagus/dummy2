<?php
/**
 * Routes file for Membership related API
 */

/**
 * Create new membership
 */
Route::post('/api/v1/membership/new', function()
{
    return MembershipAPIController::create()->postNewMembership();
});

/**
 * Delete membership
 */
Route::post('/api/v1/membership/delete', function()
{
    return MembershipAPIController::create()->postDeleteMembership();
});

/**
 * Update membership
 */
Route::post('/api/v1/membership/update', function()
{
    return MembershipAPIController::create()->postUpdateMembership();
});

/**
 * List/Search membership
 */
Route::get('/api/v1/membership/{search}', function()
{
    return MembershipAPIController::create()->getSearchMembership();
})->where('search', '(list|search)');

/**
 * Upload membership image
 */
Route::post('/api/v1/membership-image/upload', function()
{
    return UploadAPIController::create()->postUploadMembershipImage();
});

/**
 * Delete membership image
 */
Route::post('/api/v1/membership-image/delete', function()
{
    return UploadAPIController::create()->postDeleteMembershipImage();
});
