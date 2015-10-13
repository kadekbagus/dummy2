<?php
// Get Dashboard top widget clicked
Route::get('/api/v1/dashboard/top-widget', "DashboardAPIController@getTopWidgetClick");

// Get Dashboard user connect time
Route::get('/api/v1/dashboard/user-connect-time', "DashboardAPIController@getUserConnectTime");

// Get Dashboard coupon issued vs redeemed
Route::get('/api/v1/dashboard/coupon-issued-vs-redeemed', "DashboardAPIController@getCouponIssuedVSRedeemed");

// Get Dashboard top tenant redeem
Route::get('/api/v1/dashboard/top-tenant-redeem', "DashboardAPIController@getTopTenantRedeem");