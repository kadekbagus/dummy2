<?php
/**
 * Routes file for Intermediate Merchant API
 */

/**
 * Create new merchant
 */
Route::post('/app/v1/merchant/new', 'IntermediateAuthController@Merchant_postNewMerchant');

/**
 * Delete merchant
 */
Route::post('/app/v1/merchant/delete', 'IntermediateAuthController@Merchant_postDeleteMerchant');

/**
 * Update merchant
 */
Route::post('/app/v1/merchant/update', 'IntermediateAuthController@Merchant_postUpdateMerchant');

/**
 * List and/or Search merchant
 */
Route::get('/app/v1/merchant/search', 'IntermediateAuthController@Merchant_getSearchMerchant');

/**
 * Upload Merchant Logo
 */
Route::post('/app/v1/merchant/upload/logo', 'IntermediateAuthController@Upload_postUploadMerchantLogo');

/**
 * Delete Merchant Logo
 */
Route::post('/app/v1/merchant/delete/logo', 'IntermediateAuthController@Upload_postDeleteMerchantLogo');