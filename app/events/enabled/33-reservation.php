<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Orbit\Notifications\Reservation\BrandProduct\ReservationMadeNotification;
use Orbit\Notifications\Reservation\BrandProduct\ReservationCanceledNotification;

Event::listen(
    'orbit.reservation.made',
    function($reservation) {
        if ($reservation instanceof BrandProductReservation) {
            (new ReservationMadeNotification(
                $reservation->brand_product_reservation_id
            ))->send();

            // Check for expiration?
            // should use a scheduled task instead of queue.
            // Queue::later(
            //     Carbon::parse($reservation->expired_at),
            //     'Orbit\Queue\Reservation\CheckExpiredReservationQueue',
            //     ['reservationId' => $reservation->brand_product_reservation_id]
            // );
        }
    }
);

Event::listen(
    'orbit.reservation.canceled',
    function($reservation) {

        if ($reservation instanceof BrandProductReservation) {
            (new ReservationCanceledNotification(
                $reservation->brand_product_reservation_id
            ))->send();
        }
    }
);

Event::listen(
    'orbit.reservation.declined',
    function($reservation) {

        if ($reservation instanceof BrandProductReservation) {
            $reservation->user->notify(
                new ReservationCanceledNotification($reservation)
            );
        }
    }
);

Event::listen(
    'orbit.reservation.expired',
    function($reservation) {

        if ($reservation instanceof BrandProductReservation) {
            $reservation->user->notify(
                new ReservationCanceledNotification($reservation)
            );
        }
    }
);

Event::listen(
    'orbit.reservation.done',
    function($reservation) {

        if ($reservation instanceof BrandProductReservation) {
            $reservation->user->notify(
                new ReservationCanceledNotification($reservation)
            );
        }
    }
);
