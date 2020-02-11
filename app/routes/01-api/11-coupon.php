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
 * Detail coupon
 */
Route::get('/api/v1/coupon/detail', function()
{
    return CouponAPIController::create()->getDetailCoupon();
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
    return Orbit\Controller\API\v1\Pub\Coupon\CouponListNewAPIController::create()->getCouponList();
});

Route::get('/app/v1/pub/coupon-list', ['as' => 'pub-coupon-list', 'uses' => 'IntermediatePubAuthController@Coupon\CouponListNew_getCouponList']);

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
 * Get coupon count for sidebar
 */
Route::get('/api/v1/pub/coupon/count', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponCountAPIController::create()->getCouponCount();
});

Route::get('/app/v1/pub/coupon/count', ['as' => 'pub-coupon-count', 'uses' => 'IntermediatePubAuthController@Coupon\CouponCount_getCouponCount']);

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

/**
 * Get number of coupon location
 */
Route::get('/api/v1/pub/coupon-location/total', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\NumberOfCouponLocationAPIController::create()->getNumberOfCouponLocation();
});

Route::get('/app/v1/pub/coupon-location/total', ['as' => 'pub-coupon-location-total', 'uses' => 'IntermediatePubAuthController@Coupon\NumberOfCouponLocation_getNumberOfCouponLocation']);

/**
 * List city for coupon
 */
Route::get('/api/v1/pub/coupon-city/list', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponCityAPIController::create()->getCouponCity();
});

Route::get('/app/v1/pub/coupon-city/list', ['as' => 'pub-coupon-city', 'uses' => 'IntermediatePubAuthController@Coupon\CouponCity_getCouponCity']);

/**
 * Also like List of coupon
 */
Route::get('/api/v1/pub/coupon/suggestion/list', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponAlsoLikeListAPIController::create()->getCouponList();
});

Route::get('/app/v1/pub/coupon/suggestion/list', ['as' => 'pub-coupon-suggestion-list', 'uses' => 'IntermediatePubAuthController@Coupon\CouponAlsoLikeList_getCouponList']);

/**
 * Coupon Export
 */
Route::post('/api/v1/coupon/coupon-export', function()
{

    return Orbit\Controller\API\v1\CouponExportAPIController::create()->postCouponExport();
});

Route::post('/app/v1/coupon/coupon-export', ['as' => 'coupon-export', 'uses' => 'IntermediateAuthController@CouponExport_postCouponExport']);

/**
 * List promotion location for rating form
 */
Route::get('/api/v1/pub/coupon/rating/location', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponRatingLocationAPIController::create()->getCouponRatingLocation();
});

Route::get('/app/v1/pub/coupon/rating/location', ['as' => 'coupon-rating-location', 'uses' => 'IntermediatePubAuthController@Coupon\CouponRatingLocation_getCouponRatingLocation']);

/**
 * List featured advert of a coupon
 */
Route::get('/api/v1/pub/coupon-featured/list', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponFeaturedListAPIController::create()->getCouponFeaturedList();
});

Route::get('/app/v1/pub/coupon-featured/list', ['as' => 'pub-coupon-featured-list', 'uses' => 'IntermediatePubAuthController@Coupon\CouponFeaturedList_getCouponFeaturedList']);

/**
 * Get available wallet operator for coupon
 */
Route::get('/api/v1/available-wallet-operator/list', function()
{
    return CouponAPIController::create()->getAvailableWalletOperator();
});

/**
 * List coupon payment provider
 */
Route::get('/api/v1/pub/coupon-payment-provider/list', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponPaymentProviderAPIController::create()->getCouponPaymentProvider();
});

Route::get('/app/v1/pub/coupon-payment-provider/list', ['as' => 'pub-coupon-payment-provider-list', 'uses' => 'IntermediatePubAuthController@Coupon\CouponPaymentProvider_getCouponPaymentProvider']);

/**
 * Detail coupon payment provider
 */
Route::get('/api/v1/pub/coupon-payment-provider/detail', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponPaymentDetailAPIController::create()->getCouponPaymentDetail();
});

Route::get('/app/v1/pub/coupon-payment-provider/detail', ['as' => 'pub-coupon-payment-provider-detail', 'uses' => 'IntermediatePubAuthController@Coupon\CouponPaymentDetail_getCouponPaymentDetail']);

/**
 * Coupon purchased list
 */
Route::get('/api/v1/pub/coupon-purchased/list', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponPurchasedListAPIController::create()->getCouponPurchasedList();
});

Route::get('/app/v1/pub/coupon-purchased/list', ['as' => 'pub-coupon-purchased-list', 'uses' => 'IntermediatePubAuthController@Coupon\CouponPurchasedList_getCouponPurchasedList']);


/**
 * Coupon purchased detail
 */
Route::get('/api/v1/pub/coupon-purchased/detail', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponPurchasedDetailAPIController::create()->getCouponPurchasedDetail();
});

Route::get('/app/v1/pub/coupon-purchased/detail', ['as' => 'pub-coupon-purchased-detail', 'uses' => 'IntermediatePubAuthController@Coupon\CouponPurchasedDetail_getCouponPurchasedDetail']);


/**
 * Coupon buy (for hot deals and sepulsa)
 */
Route::post('/api/v1/pub/coupon-buy', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponBuyAPIController::create()->postCouponBuy();
});

Route::post('/app/v1/pub/coupon-buy', ['as' => 'pub-coupon-buy', 'uses' => 'IntermediatePubAuthController@Coupon\CouponBuy_postCouponBuy']);

/**
 * Save coupon redeem location.
 */
Route::post('/api/v1/pub/coupon-save-redeem-location', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponSaveRedeemLocation::create()->postSaveRedeemLocation();
});

Route::post('/app/v1/pub/coupon-save-redeem-location', ['as' => 'pub-save-redeem-location', 'uses' => 'IntermediatePubAuthController@Coupon\CouponSaveRedeemLocation_postSaveRedeemLocation']);

/**
 * Create new coupon sepulsa
 */
Route::post('/api/v1/coupon-sepulsa/new', function()
{
    return CouponSepulsaAPIController::create()->postNewCoupon();
});

/**
 * Update coupon sepulsa
 */
Route::post('/api/v1/coupon-sepulsa/update', function()
{
    return CouponSepulsaAPIController::create()->postUpdateCoupon();
});

/**
 * List/Search coupon sepulsa
 */
Route::get('/api/v1/coupon-sepulsa/search', function()
{
    return CouponSepulsaAPIController::create()->getSearchCoupon();
});

/**
 * Get sepulsa voucher from token
 */
Route::get('/api/v1/voucher-sepulsa/list', function()
{
    return CouponSepulsaAPIController::create()->getVoucherSepulsaList();
});

/**
 * Get sepulsa voucher from token
 */
Route::get('/api/v1/voucher-sepulsa/detail', function()
{
    return CouponSepulsaAPIController::create()->getVoucherSepulsaDetail();
});

/**
 * Get available sepulsa token
 */
Route::get('/api/v1/available-sepulsa-token/list', function()
{
    return CouponSepulsaAPIController::create()->getAvailableSepulsaTokenList();
});

/**
 * Coupon discount code quick and dirty.
 */
Route::post('/api/v1/pub/coupon-discount-code', function()
{
    return Orbit\Controller\API\v1\Pub\Coupon\CouponDiscountCodeAPIController::create()->postCouponDiscountCode();
});

Route::post('/app/v1/pub/coupon-discount-code', ['as' => 'pub-coupon-discount-code', 'uses' => 'IntermediatePubAuthController@Coupon\CouponDiscountCode_postCouponDiscountCode']);

/**
 * Create new coupon giftn
 */
Route::post('/api/v1/coupon-giftn/new', function()
{
    return CouponGiftNAPIController::create()->postNewGiftNCoupon();
});

/**
 * Update coupon giftn
 */
Route::post('/api/v1/coupon-giftn/update', function()
{
    return CouponGiftNAPIController::create()->postUpdateGiftNCoupon();
});

/**
 * List/Search coupon giftn
 */
Route::get('/api/v1/coupon-giftn/search', function()
{
    return CouponGiftNAPIController::create()->getSearchGiftNCoupon();
});

/**
 * detail coupon giftn
 */
Route::get('/api/v1/coupon-giftn/detail', function()
{
    return CouponGiftNAPIController::create()->getDetailGiftNCoupon();
});
