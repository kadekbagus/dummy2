<?php namespace Orbit\Controller\API\v1\Reservation;

interface ReservationInterface
{
    public function make($item, $reservationData);

    public function cancel($reservation);

    public function accept($reservation);

    public function decline($reservation, $reason = 'Out of Stock');

    public function done($reservation);
}
