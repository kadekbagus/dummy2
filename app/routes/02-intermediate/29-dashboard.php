<?php

// Get Dashboard top tenant
Route::get('/app/v1/dashboard/top-tenants', "IntermediateAuthController@Dashboard_getTopTenant");

// Get Dashboard top product
Route::get('/app/v1/dashboard/top-product', "IntermediateAuthController@Dashboard_getTopProduct");

// Get Dashboard top product attribute
Route::get('/app/v1/dashboard/top-product-attribute', "IntermediateAuthController@Dashboard_getTopProductAttribute");


// Get Dashboard top product family
Route::get('/app/v1/dashboard/top-product-family', "IntermediateAuthController@Dashboard_getTopProductFamily");

// Get Dashboard user login by date
Route::get('/app/v1/dashboard/user-login-by-date', "IntermediateAuthController@Dashboard_getUserLoginByDate");

// Get Dashboard user by gender
Route::get('/app/v1/dashboard/user-by-gender', "IntermediateAuthController@Dashboard_getUserByGender");

// Get Dashboard user by age
Route::get('/app/v1/dashboard/user-by-age', "IntermediateAuthController@Dashboard_getUserByAge");

// Get Dashboard user login time
Route::get('/app/v1/dashboard/user-hourly-login', "IntermediateAuthController@Dashboard_getHourlyUserLogin");

// Get Dashboard user connect time
Route::get('/app/v1/dashboard/user-connect-time', "IntermediateAuthController@Dashboard_getUserConnectTime");

// Get Dashboard user customer last visit
Route::get('/app/v1/dashboard/customer-last-visit', "IntermediateAuthController@Dashboard_getUserLastVisit");

// Get Dashboard general customer view
Route::get('/app/v1/dashboard/general-customer-view', "IntermediateAuthController@Dashboard_getGeneralCustomerView");

// Get Dashboard general customer view
Route::get('/app/v1/dashboard/top-customer-view', "IntermediateAuthController@Dashboard_getTopCustomerView");

// Get Dashboard top widget click
Route::get('/app/v1/dashboard/top-widget', "IntermediateAuthController@Dashboard_getTopWidgetClick");

// Get Dashboard coupon issued vs redeemed
Route::get('/app/v1/dashboard/coupon-issued-vs-redeemed', "IntermediateAuthController@Dashboard_getCouponIssuedVSRedeemed");

// Get Dashboard top tenant redeem
Route::get('/app/v1/dashboard/top-tenant-redeem', "IntermediateAuthController@Dashboard_getTopTenantRedeem");

// Get Dashboard detail of top customer view
Route::get('/app/v1/dashboard/detail-top-customer-view', "IntermediateAuthController@Dashboard_getDetailTopCustomerView");

// Get Dashboard expiring
Route::get('/app/v1/dashboard/expiring-campaign', "IntermediateAuthController@Dashboard_getExpiringCampaign");

// Get Dashboard estimate total cost
Route::get('/app/v1/dashboard/estimated-total-cost', "IntermediateAuthController@Dashboard_getEstimateTotalCost");

// Get Dashboard campaign status
Route::get('/app/v1/dashboard/campaign-statuses', "IntermediateAuthController@Dashboard_getCampaignStatus");

// Get Dashboard campaign status
Route::get('/app/v1/dashboard/total-page-view', "IntermediateAuthController@Dashboard_getTotalPageView");

// Get Dashboard campaign total spending
Route::get('/app/v1/dashboard/campaign-total-spending', "IntermediateAuthController@Dashboard_getCampaignTotalSpending");

// Get Dashboard unique users
Route::get('/app/v1/dashboard/unique-user', "IntermediateAuthController@Dashboard_getUniqueUsers");

// Get Dashboard Cmpaign spending
Route::get('/app/v1/dashboard/campaign-spending', "IntermediateAuthController@Dashboard_getCampaignSpending");

// Get Dashboard Total cmpaign spending
Route::get('/app/v1/dashboard/total-spending', "IntermediateAuthController@Dashboard_getCampaignSpending");
