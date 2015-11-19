<?php
/**
 * Routes file for Intermediate Membership API
 */

/**
 * Create new membership
 */
Route::post('/app/v1/membership/new', 'IntermediateAuthController@Membership_postNewMembership');

/**
 * Delete membership
 */
Route::post('/app/v1/membership/delete', 'IntermediateAuthController@Membership_postDeleteMembership');

/**
 * Update membership
 */
Route::post('/app/v1/membership/update', 'IntermediateAuthController@Membership_postUpdateMembership');

/**
 * List and/or Search membership
 */
Route::get('/app/v1/membership/{search}', 'IntermediateAuthController@Membership_getSearchMembership')
     ->where('search', '(list|search)');

/**
 * Upload membership image
 */
Route::post('/app/v1/membership-image/upload', 'IntermediateAuthController@Upload_postUploadMembershipImage');

/**
 * Delete membership image
 */
Route::post('/app/v1/membership-image/delete', 'IntermediateAuthController@Upload_postDeleteMembershipImage');
