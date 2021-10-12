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

Route::get(
    '/app/v1/pub/reservation-purchased/list',
    [
        'as' => 'reservation-purchased-list',
        'uses' => 'IntermediatePubAuthController@Reservation\ReservationPurchasedList_getReservationPurchasedList',
    ]
);

Route::get(
    '/app/v1/pub/reservation-purchased/detail',
    [
        'as' => 'reservation-purchased-detail',
        'uses' => 'IntermediatePubAuthController@Reservation\ReservationPurchasedDetail_getReservationPurchasedDetail',
    ]
);

Route::post('/app/v1/pub/reservation/picked-up', [
    'as' => 'pub-reservation-picked-up',
    'uses' => 'IntermediatePubAuthController@Reservation\ReservationPickedUp_handle',
]);
