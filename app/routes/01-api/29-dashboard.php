<?php
// Get Dashboard top widget clicked
Route::get('/api/v1/dashboard/top-widget', "DashboardAPIController@getTopWidgetClick");

// Get Dashboard user connect time
Route::get('/api/v1/dashboard/user-connect-time', "DashboardAPIController@getUserConnectTime");

// Get Dashboard coupon issued vs reedemed
Route::get('/api/v1/dashboard/coupon-issued-vs-reedemed', "DashboardAPIController@getCouponIssuedVSReedemed");