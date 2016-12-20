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
    return Orbit\Controller\API\v1\Pub\Coupon\CouponListAPIController::create()->getCouponList();
});

Route::get('/app/v1/pub/coupon-list', ['as' => 'pub-coupon-list', 'uses' => 'IntermediatePubAuthController@Coupon\CouponList_getCouponList']);

/**
 * Get mall list after click coupon
 */
Route::get('/api/v1/pub/mall-coupon-list', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponMallListAPIController::create()->getMallCouponList();
});

Route::get('/app/v1/pub/mall-coupon-list', ['as' => 'pub-mall-coupon-list', 'uses' => 'IntermediatePubAuthController@Coupon\CouponMallList_getMallCouponList']);

/**
 * Get coupon detail on landing page
 */
Route::get('/api/v1/pub/coupon/detail', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponDetailAPIController::create()->getCouponItem();
});

Route::get('/app/v1/pub/coupon/detail', ['as' => 'pub-coupon-detail', 'uses' => 'IntermediatePubAuthController@Coupon\CouponDetail_getCouponItem']);

/**
 * List location of a coupon
 */
Route::get('/api/v1/pub/coupon-location/list', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponLocationAPIController::create()->getCouponLocations();
});

Route::get('/app/v1/pub/coupon-location/list', ['as' => 'pub-coupon-location-list', 'uses' => 'IntermediatePubAuthController@Coupon\CouponLocation_getCouponLocations']);

/**
 * Get coupon wallet list on landing page
 */
Route::get('/api/v1/pub/mall-coupon-wallet/list', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponWalletListAPIController::create()->getCouponWalletList();
});

Route::get('/app/v1/pub/mall-coupon-wallet/list', ['as' => 'pub-mall-coupon-wallet-list', 'uses' => 'IntermediatePubAuthController@Coupon\CouponWalletList_getCouponWalletList']);

/**
 * Get coupon wallet location list on landing page
 */
Route::get('/api/v1/pub/mall-coupon-wallet-location/list', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponWalletLocationAPIController::create()->getCouponWalletLocations();
});

Route::get('/app/v1/pub/mall-coupon-wallet-location/list', ['as' => 'pub-mall-coupon-wallet-location-list', 'uses' => 'IntermediatePubAuthController@Coupon\CouponWalletLocation_getCouponWalletLocations']);

/**
 * Post issue coupon wallet on landing page
 */
Route::post('/api/v1/pub/add-to-wallet', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponAddToWalletAPIController::create()->postAddToWallet();
});

Route::post('/app/v1/pub/add-to-wallet', ['as' => 'pub-mall-coupon-add-to-wallet', 'uses' => 'IntermediatePubAuthController@Coupon\CouponAddToWallet_postAddToWallet']);

/**
 * Post issue coupon to email on landing page
 */
Route::post('/api/v1/pub/coupon/send-to-email', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponAddToEmailAPIController::create()->postAddCouponToEmail();
});

Route::post('/app/v1/pub/coupon/send-to-email', ['as' => 'pub-mall-coupon-add-to-email', 'uses' => 'IntermediatePubAuthController@Coupon\CouponAddToEmail_postAddCouponToEmail']);

/**
 * Get coupon redemption detail page on landing page
 */
Route::get('/api/v1/pub/coupon/redemption', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponRedemptionPageAPIController::create()->getCouponItemRedemption();
});

Route::get('/app/v1/pub/coupon/redemption', ['as' => 'pub-mall-coupon-redemption', 'uses' => 'IntermediatePubAuthController@Coupon\CouponRedemptionPage_getCouponItemRedemption']);

/**
 * Get coupon redemption detail page on landing page
 */
Route::post('/api/v1/pub/coupon/redeem', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponRedeemAPIController::create()->postPubRedeemCoupon();
});

Route::post('/app/v1/pub/coupon/redeem', ['as' => 'pub-mall-coupon-redeem', 'uses' => 'IntermediatePubAuthController@Coupon\CouponRedeem_postPubRedeemCoupon']);


/**
 * Check validity of issued coupon from sms
 */
Route::get('/api/v1/pub/coupon/canvas', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponCanvasAPIController::create()->getCheckValidityCoupon();
});

Route::get('/app/v1/pub/coupon/canvas', ['as' => 'pub-mall-coupon-canvas', 'uses' => 'IntermediatePubAuthController@Coupon\CouponCanvas_getCheckValidityCoupon']);

/**
 * List store of a coupon
 */
Route::get('/api/v1/pub/store-coupon/list', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponStoreAPIController::create()->getCouponStore();
});

Route::get('/app/v1/pub/store-coupon/list', ['as' => 'pub-store-coupon-list', 'uses' => 'IntermediatePubAuthController@Coupon\CouponStore_getCouponStore']);