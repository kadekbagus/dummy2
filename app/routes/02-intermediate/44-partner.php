<?php
/**
 * Routes file for Intermediate Partner API
 */

/**
 * Create new partner
 */
Route::post('/app/v1/partner/new', 'IntermediateAuthController@Partner_postNewPartner');

/**
 * Update partner
 */
Route::post('/app/v1/partner/update', 'IntermediateAuthController@Partner_postUpdatePartner');

/**
 * Get search partner
 */
Route::get('/app/v1/partner/list', 'IntermediateAuthController@Partner_getSearchPartner');

/**
 * Pub get partner detail
 */
Route::get('/app/v1/pub/partner/detail', ['as' => 'pub-partner-detail', 'uses' => 'IntermediatePubAuthController@Partner\PartnerDetail_getPartnerDetail']);

/**
 * Upload partner Logo
 */
Route::post('/app/v1/partner/upload/logo', 'IntermediateAuthController@Upload_postUploadPartnerLogo');

/**
 * Upload partner Image
 */
Route::post('/app/v1/partner/upload/image', 'IntermediateAuthController@Upload_postUploadPartnerImage');

/**
 * Delete partner Logo
 */
Route::post('/app/v1/partner/delete/logo', 'IntermediateAuthController@Upload_postDeletePartnerLogo');

/**
 * Delete partner Logo
 */
Route::post('/app/v1/partner/delete/image', 'IntermediateAuthController@Upload_postDeletePartnerImage');
