<?php

Route::get('/api/v1/cust/coupons', function()
{
    return Orbit\Controller\API\v1\Customer\CouponCIAPIController::create()->getCouponList();
});

Route::get('/api/v1/cust/coupons/detail', function()
{
    return Orbit\Controller\API\v1\Customer\CouponCIAPIController::create()->getCouponItem();
});

Route::get('/app/v1/cust/coupons', ['as' => 'customer-api-coupon-list', 'uses' => 'IntermediateCIAuthController@CouponCI_getCouponList']);

Route::get('/app/v1/cust/coupons/detail', ['as' => 'customer-api-coupon-detail', 'uses' => 'IntermediateCIAuthController@CouponCI_getCouponItem']);

Route::post('/app/v1/cust/coupons/redeem', 'IntermediateCIAuthController@Coupon_postRedeemCoupon');