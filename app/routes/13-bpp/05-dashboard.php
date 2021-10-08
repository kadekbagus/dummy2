<?php

// Route for total amount
Route::get('/app/v1/brand-product/dashboard/total-amount', ['as' => 'bpp-dash-total-amount', 'uses' => 'IntermediateBrandProductAuthController@Dashboard\TotalAmount_get']);
