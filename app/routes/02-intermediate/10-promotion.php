<?php
/**
 * Routes file for Intermediate Promotion API
 */

/**
 * Create new promotion
 */
Route::post('/app/v1/promotion/new', 'IntermediateAuthController@Promotion_postNewPromotion');

/**
 * Delete promotion
 */
Route::post('/app/v1/promotion/delete', 'IntermediateAuthController@Promotion_postDeletePromotion');

/**
 * Update promotion
 */
Route::post('/app/v1/promotion/update', 'IntermediateAuthController@Promotion_postUpdatePromotion');

/**
 * List and/or Search promotion
 */
Route::get('/app/v1/promotion/search', 'IntermediateAuthController@Promotion_getSearchPromotion');

/**
 * List and/or Search promotion by retailer
 */
Route::get('/app/v1/promotion/by-retailer/search', 'IntermediateAuthController@Promotion_getSearchPromotionByRetailer');

/**
 * Upload promotion Image
 */
Route::post('/app/v1/promotion/upload/image', 'IntermediateAuthController@Upload_postUploadPromotionImage');

/**
 * Delete promotion Image
 */
Route::post('/app/v1/promotion/delete/image', 'IntermediateAuthController@Upload_postDeletePromotionImage');