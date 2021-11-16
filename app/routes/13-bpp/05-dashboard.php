<?php

// Route for total amount
Route::get('/app/v1/brand-product/dashboard/total-amount', ['as' => 'bpp-dash-total-amount', 'uses' => 'IntermediateBrandProductAuthController@Dashboard\TotalAmount_get']);

// Route for count order
Route::get('/app/v1/brand-product/dashboard/count-order', ['as' => 'bpp-dash-count-order', 'uses' => 'IntermediateBrandProductAuthController@Dashboard\TotalOrder_get']);

// Route for total reservation
Route::get('/app/v1/brand-product/dashboard/count-reservation', ['as' => 'bpp-dash-count-reservation', 'uses' => 'IntermediateBrandProductAuthController@Dashboard\TotalReservation_get']);

// Route for average order amount
Route::get('/app/v1/brand-product/dashboard/average-order', ['as' => 'bpp-dash-avg-order', 'uses' => 'IntermediateBrandProductAuthController@Dashboard\AverageAmount_get']);

// Route for top 5 viewed products
Route::get('/app/v1/brand-product/dashboard/top-five-product', ['as' => 'bpp-dash-top-five-product', 'uses' => 'IntermediateBrandProductAuthController@Dashboard\TopFiveProduct_get']);

// Route for total product page views
Route::get('/app/v1/brand-product/dashboard/total-views', ['as' => 'bpp-dash-total-views', 'uses' => 'IntermediateBrandProductAuthController@Dashboard\TotalViewProduct_get']);

// Route for conversion rate
Route::get('/app/v1/brand-product/dashboard/conversion-rate', ['as' => 'bpp-dash-conversion-rate', 'uses' => 'IntermediateBrandProductAuthController@Dashboard\ConversionRate_get']);

// Route for daily order amount chart
Route::get('/app/v1/brand-product/dashboard/daily-amount', ['as' => 'bpp-dash-daily-amount', 'uses' => 'IntermediateBrandProductAuthController@Dashboard\OrderAmountSeries_get']);

// Route for total visitor
Route::get('/app/v1/brand-product/dashboard/total-visitor', ['as' => 'bpp-dash-total-visitor', 'uses' => 'IntermediateBrandProductAuthController@Dashboard\TotalVisitor_get']);

// Route for total sold order
Route::get('/app/v1/brand-product/dashboard/total-sold-order', ['as' => 'bpp-dash-total-sold-order', 'uses' => 'IntermediateBrandProductAuthController@Dashboard\TotalSoldOrder_get']);

// Route for total sold reservation
Route::get('/app/v1/brand-product/dashboard/total-sold-reservation', ['as' => 'bpp-dash-total-sold-reservation', 'uses' => 'IntermediateBrandProductAuthController@Dashboard\TotalSoldReservation_get']);

