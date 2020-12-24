<?php

Route::post(
    '/app/v1/pub/reservation',
    [
        'as' => 'reservation-new',
        'uses' => 'IntermediatePubAuthController@Reservation\ReservationNew_handle',
    ]
);

Route::get(
    '/app/v1/pub/reservation/detail',
    [
        'as' => 'reservation-detail',
        'uses' => 'IntermediatePubAuthController@Reservation\ReservationDetail_handle',
    ]
);

Route::post(
    '/app/v1/pub/reservation/cancel',
    [
        'as' => 'reservation-cancel',
        'uses' => 'IntermediatePubAuthController@Reservation\ReservationCancel_handle',
    ]
);
