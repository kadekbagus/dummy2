<?php

// Route for product new
Route::get('/app/v1/brand-product/reservations/list', ['as' => 'brand-reservation-list', 'uses' => 'IntermediateBrandProductAuthController@Reservation\ReservationList_getSearchReservation']);