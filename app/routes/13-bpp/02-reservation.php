<?php

// Route for reservation list
Route::get('/app/v1/brand-product/reservations/list', ['as' => 'brand-reservation-list', 'uses' => 'IntermediateBrandProductAuthController@Reservation\ReservationList_getSearchReservation']);

// Route for reservation detail
Route::get('/app/v1/brand-product/reservations/detail', ['as' => 'brand-reservation-list', 'uses' => 'IntermediateBrandProductAuthController@Reservation\ReservationDetail_getReservationDetail']);

// Route for reservation update status
Route::post('/app/v1/brand-product/reservations/update/status', ['as' => 'brand-reservation-update-status', 'uses' => 'IntermediateBrandProductAuthController@Reservation\ReservationUpdateStatus_postUpdate']);
