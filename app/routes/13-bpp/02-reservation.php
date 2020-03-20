<?php

// Route for reservation list
Route::get('/app/v1/brand-product/reservations/list', ['as' => 'brand-reservation-list', 'uses' => 'IntermediateBrandProductAuthController@Reservation\ReservationList_getSearchReservation']);

// Route for reservation detail
Route::get('/app/v1/brand-product/reservations/detail', ['as' => 'brand-reservation-list', 'uses' => 'IntermediateBrandProductAuthController@Reservation\ReservationDetail_getReservationDetail']);
