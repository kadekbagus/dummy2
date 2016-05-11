<?php

// Get Dashboard top tenant
Route::get('/api/v1/dashboard/top-tenants', "DashboardAPIController@getTopTenant");

// Get Dashboard top product
Route::get('/api/v1/dashboard/top-product', "DashboardAPIController@getTopProduct");

// Get Dashboard top product attribute
Route::get('/api/v1/dashboard/top-product-attribute', "DashboardAPIController@getTopProductAttribute");

// Get Dashboard top product family
Route::get('/api/v1/dashboard/top-product-family', "DashboardAPIController@getTopProductFamily");

// Get Dashboard user login by date
Route::get('/api/v1/dashboard/user-login-by-date', "DashboardAPIController@getUserLoginByDate");

// Get Dashboard user by gender
Route::get('/api/v1/dashboard/user-by-gender', "DashboardAPIController@getUserByGender");

// Get Dashboard user by age
Route::get('/api/v1/dashboard/user-by-age', "DashboardAPIController@getUserByAge");

// Get Dashboard user login time
Route::get('/api/v1/dashboard/user-hourly-login', "DashboardAPIController@getHourlyUserLogin");

// Get Dashboard user connect time
Route::get('/api/v1/dashboard/user-connect-time', "DashboardAPIController@getUserConnectTime");

// Get Dashboard user customer last visit
Route::get('/api/v1/dashboard/customer-last-visit', "DashboardAPIController@getUserLastVisit");

// Get Dashboard user customer merchant summary
Route::get('/api/v1/dashboard/customer-merchant-summary', "DashboardAPIController@getUserMerchantSummary");


// Get Dashboard user customer merchant summary
Route::get('/api/v1/dashboard/customer-merchant-summary', "DashboardAPIController@getUserMerchantSummary");

// Get Dashboard general customer view
Route::get('/api/v1/dashboard/general-customer-view', "DashboardAPIController@getGeneralCustomerView");

// Get Dashboard general customer view
Route::get('/api/v1/dashboard/top-customer-view', "DashboardAPIController@getTopCustomerView");

// Get Dashboard top widget clicked
Route::get('/api/v1/dashboard/top-widget', "DashboardAPIController@getTopWidgetClick");

// Get Dashboard coupon issued vs redeemed
Route::get('/api/v1/dashboard/coupon-issued-vs-redeemed', "DashboardAPIController@getCouponIssuedVSRedeemed");

// Get Dashboard top tenant redeem
Route::get('/api/v1/dashboard/top-tenant-redeem', "DashboardAPIController@getTopTenantRedeem");

// Get Dashboard detail of top customer view
Route::get('/api/v1/dashboard/detail-top-customer-view', "DashboardAPIController@getDetailTopCustomerView");

// Get Dashboard expiting campaigns
Route::get('/api/v1/dashboard/expiring-campaign', "DashboardAPIController@getExpiringCampaign");

// Get Dashboard estimate total cost
Route::get('/api/v1/dashboard/estimated-total-cost', "DashboardAPIController@getEstimateTotalCost");

// Get Dashboard campaign status
Route::get('/api/v1/dashboard/campaign-statuses', "DashboardAPIController@getCampaignStatus");

// Get Dashboard total page view
Route::get('/api/v1/dashboard/total-page-view', "DashboardAPIController@getTotalPageView");

// Get Dashboard campaign total spending
Route::get('/api/v1/dashboard/campaign-total-spending', "DashboardAPIController@getCampaignTotalSpending");

// Get Dashboard unique users
Route::get('/api/v1/dashboard/unique-user', "DashboardAPIController@getUniqueUsers");

// Get Dashboard Campaign spending
Route::get('/api/v1/dashboard/campaign-spending', "DashboardAPIController@getCampaignSpending");

// Get Dashboard Total campaign spending
Route::get('/api/v1/dashboard/total-spending', "DashboardAPIController@getCampaignSpending");
