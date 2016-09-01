<?php
/**
 * Routes file for Coupon related API
 */

/**
 * Create new coupon
 */
Route::post('/api/v1/coupon/new', function()
{
    return CouponAPIController::create()->postNewCoupon();
});

/**
 * Delete coupon
 */
Route::post('/api/v1/coupon/delete', function()
{
    return CouponAPIController::create()->postDeleteCoupon();
});

/**
 * Update coupon
 */
Route::post('/api/v1/coupon/update', function()
{
    return CouponAPIController::create()->postUpdateCoupon();
});

/**
 * List/Search coupon
 */
Route::get('/api/v1/coupon/search', function()
{
    return CouponAPIController::create()->getSearchCoupon();
});

/**
 * List/Search coupon by issue retailer
 */
Route::get('/api/v1/coupon/by-issue-retailer/search', function()
{
    return CouponAPIController::create()->getSearchCouponByIssueRetailer();
});

/**
 * Upload coupon image
 */
Route::post('/api/v1/coupon/upload/image', function()
{
    return UploadAPIController::create()->postUploadCouponImage();
});

/**
 * Delete coupon image
 */
Route::post('/api/v1/coupon/delete/image', function()
{
    return UploadAPIController::create()->postDeleteCouponImage();
});

/**
 * Create new issued coupon
 */
Route::post('/api/v1/issued-coupon/new', function()
{
    return IssuedCouponAPIController::create()->postNewIssuedCoupon();
});

/**
 * Update issued coupon
 */
Route::post('/api/v1/issued-coupon/update', function()
{
    return IssuedCouponAPIController::create()->postUpdateIssuedCoupon();
});

/**
 * Update issued coupon
 */
Route::post('/api/v1/issued-coupon/delete', function()
{
    return IssuedCouponAPIController::create()->postDeleteIssuedCoupon();
});

/**
 * List issued coupon
 */
Route::get('/api/v1/issued-coupon/search', function()
{
    return IssuedCouponAPIController::create()->getSearchIssuedCoupon();
});

/**
 * List issued coupon by redeem retailer
 */
Route::get('/api/v1/issued-coupon/by-redeem-retailer/search', function()
{
    return IssuedCouponAPIController::create()->getSearchIssuedCouponByRedeemRetailer();
});

/**
 * Redeem coupon for consumer
 */
Route::post('/api/v1/issued-coupon/redeem', function()
{
    return CouponAPIController::create()->postRedeemCoupon();
});

/**
 * Coupon Report By Name
 */
Route::post('/api/v1/coupon-report/list', function()
{
    return CouponReportAPIController::create()->getCouponReport();
});

/**
 * Route to get CS by coupon ID
 */
Route::get('/api/v1/coupon/customer-service', ['as' => 'api-coupon-customer-service', function()
{
        return Orbit\Controller\API\v1\CSListByCouponAPIController::create()->getList();
}]);

/**
 * Get coupon list
 */
Route::get('/api/v1/pub/coupon-list', function()
{
    return Orbit\Controller\API\v1\Pub\CouponAPIController::create()->getCouponList();
});

Route::get('/app/v1/pub/coupon-list', ['as' => 'pub-coupon-list', 'uses' => 'IntermediatePubAuthController@Coupon_getCouponList']);

/**
 * Get mall list after click coupon
 */
Route::get('/api/v1/pub/mall-coupon-list', function()
{
    return Orbit\Controller\API\v1\Pub\CouponAPIController::create()->getMallCouponList();
});

Route::get('/app/v1/pub/mall-coupon-list', ['as' => 'pub-mall-coupon-list', 'uses' => 'IntermediatePubAuthController@Coupon_getMallCouponList']);

/**
 * Get coupon detail on landing page
 */
Route::get('/api/v1/pub/coupon/detail', function()
{
    return Orbit\Controller\API\v1\Pub\CouponAPIController::create()->getCouponItem();
});

Route::get('/app/v1/pub/coupon/detail', ['as' => 'pub-coupon-detail', 'uses' => 'IntermediatePubAuthController@Coupon_getCouponItem']);

/**
 * List location of a coupon
 */
Route::get('/api/v1/pub/coupon-location/list', function()
{
    return Orbit\Controller\API\v1\Pub\CouponAPIController::create()->getMallPerCoupon();
});

Route::get('/app/v1/pub/coupon-location/list', ['as' => 'pub-coupon-location-list', 'uses' => 'IntermediatePubAuthController@Coupon_getCouponLocations']);