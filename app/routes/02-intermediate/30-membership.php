<?php
/**
 * Routes file for Intermediate Membership API
 */

/**
 * Create new membership
 */
Route::post('/app/v1/membership-card/new', 'IntermediateAuthController@Membership_postNewMembership');

/**
 * Delete membership
 */
Route::post('/app/v1/membership-card/delete', 'IntermediateAuthController@Membership_postDeleteMembership');

/**
 * Update membership
 */
Route::post('/app/v1/membership-card/update', 'IntermediateAuthController@Membership_postUpdateMembership');

/**
 * List and/or Search membership
 */
Route::get('/app/v1/membership-card/{search}', 'IntermediateAuthController@Membership_getSearchMembership')
     ->where('search', '(list|search)');

/**
 * Upload membership image
 */
Route::post('/app/v1/membership-card-image/upload', 'IntermediateAuthController@Upload_postUploadMembershipImage');

/**
 * Delete membership image
 */
Route::post('/app/v1/membership-card-image/delete', 'IntermediateAuthController@Upload_postDeleteMembershipImage');
