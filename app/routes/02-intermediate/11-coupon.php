<?php
/**
 * Routes file for Intermediate Coupon API
 */

/**
 * Create new coupon
 */
Route::post('/app/v1/coupon/new', 'IntermediateAuthController@Coupon_postNewCoupon');

/**
 * Delete coupon
 */
Route::post('/app/v1/coupon/delete', 'IntermediateAuthController@Coupon_postDeleteCoupon');

/**
 * Update coupon
 */
Route::post('/app/v1/coupon/update', 'IntermediateAuthController@Coupon_postUpdateCoupon');

/**
 * List and/or Search coupon
 */
Route::get('/app/v1/coupon/search', 'IntermediateAuthController@Coupon_getSearchCoupon');

/**
 * List and/or Search coupon by issue retailer
 */
Route::get('/app/v1/coupon/by-issue-retailer/search', 'IntermediateAuthController@Coupon_getSearchCouponByIssueRetailer');

/**
 * Upload coupon Image
 */
Route::post('/app/v1/coupon/upload/image', 'IntermediateAuthController@Upload_postUploadCouponImage');

/**
 * Delete coupon Image
 */
Route::post('/app/v1/coupon/delete/image', 'IntermediateAuthController@Upload_postDeleteCouponImage');

/**
 * Create new issued coupon
 */
Route::post('/app/v1/issued-coupon/new', 'IntermediateAuthController@IssuedCoupon_postNewIssuedCoupon');

/**
 * Update issued coupon
 */
Route::post('/app/v1/issued-coupon/update', 'IntermediateAuthController@IssuedCoupon_postUpdateIssuedCoupon');

/**
 * Delete issued coupon
 */
Route::post('/app/v1/issued-coupon/delete', 'IntermediateAuthController@IssuedCoupon_postDeleteIssuedCoupon');

/**
 * List issued coupon
 */
Route::get('/app/v1/issued-coupon/search', 'IntermediateAuthController@IssuedCoupon_getSearchIssuedCoupon');

/**
 * List issued coupon by redeem retailer
 */
Route::get('/app/v1/issued-coupon/by-redeem-retailer/search', 'IntermediateAuthController@IssuedCoupon_getSearchIssuedCouponByRedeemRetailer');

/**
 * Redeem issued coupon for consumer
 */
Route::post('/app/v1/issued-coupon/redeem', 'IntermediateAuthController@Coupon_postRedeemCoupon');
