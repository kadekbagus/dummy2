<?php

// Route for total amount
Route::get('/app/v1/brand-product/dashboard/total-amount', ['as' => 'bpp-dash-total-amount', 'uses' => 'IntermediateBrandProductAuthController@Dashboard\TotalAmount_get']);

// Route for count order
Route::get('/app/v1/brand-product/dashboard/count-order', ['as' => 'bpp-dash-count-order', 'uses' => 'IntermediateBrandProductAuthController@Dashboard\TotalOrder_get']);

// Route for total reservation
Route::get('/app/v1/brand-product/dashboard/count-reservation', ['as' => 'bpp-dash-count-reservation', 'uses' => 'IntermediateBrandProductAuthController@Dashboard\TotalReservation_get']);