<?php
/**
 * Routes file for Intermediate Retailer API
 */

/**
 * Create new retailer
 */
Route::post('/app/v1/retailer/new', 'IntermediateAuthController@Retailer_postNewRetailer');

/**
 * Delete retailer
 */
Route::post('/app/v1/retailer/delete', 'IntermediateAuthController@Retailer_postDeleteRetailer');

/**
 * Update retailer
 */
Route::post('/app/v1/retailer/update', 'IntermediateAuthController@Retailer_postUpdateRetailer');

/**
 * List and/or Search retailer
 */
Route::get('/app/v1/retailer/search', 'IntermediateAuthController@Retailer_getSearchRetailer');

/**
 * Retailer city list
 */
Route::get('/app/v1/retailer/city', 'IntermediateAuthController@Retailer_getCityList');