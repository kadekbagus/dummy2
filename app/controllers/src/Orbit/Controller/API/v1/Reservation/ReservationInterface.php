<?php namespace Orbit\Controller\API\v1\Reservation;

interface ReservationInterface
{
    /**
     * Get reservation.
     *
     * @return Model reservation model.
     */
    public function get($id);

    /**
     * Make a reservation.
     */
    public function make($item, $reservationData);

    /**
     * Cancel a reservation.
     *
     * @return Model reservation model.
     */
    public function cancel($reservation);

    /**
     * Accept a reservation.
     *
     * @return Model reservation model.
     */
    public function accept($reservation);

    /**
     * Decline a reservation.
     *
     * @return Model reservation model.
     */
    public function decline($reservation, $reason = 'Out of Stock');

    /**
     * Complete the reservation.
     *
     * @return Model reservation model
     */
    public function done($reservation);

    /**
     * Determine that the reservation is accepted.
     *
     * @return bool accepted or not.
     */
    public function accepted($reservation);

    public function pickedUp($reservation);
}
