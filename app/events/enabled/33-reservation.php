<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Orbit\Notifications\Reservation\BrandProduct\ReservationMadeNotification;
use Orbit\Notifications\Reservation\BrandProduct\ReservationExpiredNotification;
use Orbit\Notifications\Reservation\BrandProduct\ReservationAcceptedNotification;
use Orbit\Notifications\Reservation\BrandProduct\ReservationCanceledNotification;
use Orbit\Notifications\Reservation\BrandProduct\ReservationDeclinedNotification;
use Orbit\Notifications\Reservation\BrandProduct\ReservationExpiredAdminNotification;

Event::listen(
    'orbit.reservation.made',
    function($reservation) {
        if ($reservation instanceof BrandProductReservation) {
            (new ReservationMadeNotification(
                $reservation->brand_product_reservation_id
            ))->send();
        }
    }
);

Event::listen(
    'orbit.reservation.made_multiple',
    function($reservations = []) {
        foreach($reservations as $reservation) {
            // Event::fire('orbit.reservation.made', [$reservation]);
            if ($reservation instanceof BrandProductReservation) {
                (new ReservationMadeNotification(
                    $reservation->brand_product_reservation_id
                ))->send();
            }
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
    function($reservation, $reason = 'Out of Stock') {

        if ($reservation instanceof BrandProductReservation) {
            (new ReservationDeclinedNotification(
                $reservation->brand_product_reservation_id
            ))->send();
        }
    }
);

Event::listen(
    'orbit.reservation.expired',
    function($reservation) {

        if ($reservation instanceof BrandProductReservation) {
            (new ReservationExpiredNotification(
                $reservation->brand_product_reservation_id
            ))->send();

            (new ReservationExpiredAdminNotification(
                $reservation->brand_product_reservation_id
            ))->send();
        }
    }
);

Event::listen(
    'orbit.reservation.accepted',
    function($reservation) {

        if ($reservation instanceof BrandProductReservation) {
            (new ReservationAcceptedNotification(
                $reservation->brand_product_reservation_id
            ))->send();

            // Check for expiration?
            // should use a scheduled task instead of queue?
            Queue::later(
                Carbon::parse($reservation->expired_at),
                'Orbit\Queue\Reservation\CheckExpiredReservationQueue',
                [
                    'reservationId' => $reservation->brand_product_reservation_id,
                    'type' => 'brand_product',
                ]
            );
        }
    }
);
