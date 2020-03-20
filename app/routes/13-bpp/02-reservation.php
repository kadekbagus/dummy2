<?php

// Route for product new
Route::post('/app/v1/brand-product/reservations/list', ['as' => 'brand-reservation-list', 'uses' => 'IntermediateBrandProductAuthController@ReservationList_getSearchReservation']);