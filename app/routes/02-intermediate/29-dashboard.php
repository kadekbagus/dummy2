<?php
// Get Dashboard top widget click
Route::get('/app/v1/dashboard/top-widget', "IntermediateAuthController@Dashboard_getTopWidgetClick");

// Get Dashboard user connect time
Route::get('/app/v1/dashboard/user-connect-time', "IntermediateAuthController@Dashboard_getUserConnectTime");

// Get Dashboard coupon issued vs redeemed
Route::get('/app/v1/dashboard/coupon-issued-vs-redeemed', "IntermediateAuthController@Dashboard_getCouponIssuedVSRedeemed");

// Get Dashboard top tenant redeem
Route::get('/app/v1/dashboard/top-tenant-redeem', "IntermediateAuthController@Dashboard_getTopTenantRedeem");