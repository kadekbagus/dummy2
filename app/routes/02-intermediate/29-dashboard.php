<?php

// Get Dashboard top tenant
Route::get('/app/v1/dashboard/top-tenants', "IntermediateAuthController@Dashboard_getTopTenant");

// Get Dashboard top product
Route::get('/app/v1/dashboard/top-product', "IntermediateAuthController@Dashboard_getTopProduct");

// Get Dashboard top product attribute
Route::get('/app/v1/dashboard/top-product-attribute', "IntermediateAuthController@Dashboard_getTopProductAttribute");


// Get Dashboard top product family
Route::get('/app/v1/dashboard/top-product-family', "IntermediateAuthController@Dashboard_getTopProductFamily");

// Get Dashboard top widget click
Route::get('/app/v1/dashboard/top-widget', "IntermediateAuthController@Dashboard_getTopWidgetClick");

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
