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
 * Detail coupon
 */
Route::get('/app/v1/coupon/detail', 'IntermediateAuthController@Coupon_getDetailCoupon');

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

/**
 * Report Coupon By Name
 */
Route::get('/app/v1/coupon-report/list', 'IntermediateAuthController@CouponReport_getCouponReport');

/**
 * Route to get CS by coupon ID
 */
Route::get('/app/v1/coupon/customer-service', 'IntermediateAuthController@CSListByCoupon_getList');


/**
 * Get available wallet operator for coupon
 */
Route::get('/app/v1/available-wallet-operator/list', 'IntermediateAuthController@Coupon_getAvailableWalletOperator');

/**
 * Create new coupon sepulsa
 */
Route::post('/app/v1/coupon-sepulsa/new', 'IntermediateAuthController@CouponSepulsa_postNewCoupon');

/**
 * Update coupon sepulsa
 */
Route::post('/app/v1/coupon-sepulsa/update', 'IntermediateAuthController@CouponSepulsa_postUpdateCoupon');

/**
 * List and/or Search coupon sepulsa
 */
Route::get('/app/v1/coupon-sepulsa/search', 'IntermediateAuthController@CouponSepulsa_getSearchCoupon');

/**
 * Get sepulsa voucher from token
 */
Route::get('/app/v1/voucher-sepulsa/list', 'IntermediateAuthController@CouponSepulsa_getVoucherSepulsaList');

/**
 * Get sepulsa voucher from token
 */
Route::get('/app/v1/voucher-sepulsa/detail', 'IntermediateAuthController@CouponSepulsa_getVoucherSepulsaDetail');

/**
 * Get available sepulsa token
 */
Route::get('/app/v1/available-sepulsa-token/list', 'IntermediateAuthController@CouponSepulsa_getAvailableSepulsaTokenList');


/**
 * Create new coupon giftn
 */
Route::post('/app/v1/coupon-giftn/new', 'IntermediateAuthController@CouponGiftN_postNewGiftNCoupon');

/**
 * Update coupon giftn
 */
Route::post('/app/v1/coupon-giftn/update', 'IntermediateAuthController@CouponGiftN_postUpdateGiftNCoupon');

/**
 * List and/or Search coupon giftn
 */
Route::get('/app/v1/coupon-giftn/search', 'IntermediateAuthController@CouponGiftN_getSearchGiftNCoupon');

/**
 * Detail coupon giftn
 */
Route::get('/app/v1/coupon-giftn/detail', 'IntermediateAuthController@CouponGiftN_getDetailGiftNCoupon');