<?php

namespace Orbit\Controller\API\v1\BrandProduct\Repository\Handlers;

use App;
use DB;

/**
 * A helper that provide Brand Product reservation routines.
 *
 * @author Budi <budi@gotomalls.com>
 */
trait HandleReservation
{
    /**
     * Reserve a product.
     *
     * @param  [type] $brandProductVariantId [description]
     * @param  [type] $request               [description]
     * @return [type]                        [description]
     */
    public function reserve($data)
    {
        $reservation = null;

        DB::transaction(function() use ($reservation, $data)
        {
            $reservation = new ProductReservation;
            $reservation->brand_product_variant_id = $data['variant_id'];
            $reservation->option_type = $data['option_type'];
            $reservation->option_id = $data['option_id'];

            $reservation->save();
        });

        return $reservation;
    }

    /**
     * Cancel product reservation.
     *
     * @param  [type] $brandProductId [description]
     * @return [type]                 [description]
     */
    public function cancelReservation($reservationId)
    {
        $reservation = null;

        DB::transaction(function() use ($reservation, $reservationId)
        {
            $reservation = ProductReservation::findOrFail($reservationId);
            $reservation->status = ProductReservation::STATUS_CANCELLED;
            $reservation->cancelled_by = App::make('currentUser')->user_id;
            $reservation->save();
        });

        return $reservation;
    }

    /**
     * Accept a Reservation.
     *
     * @param  [type] $reservationId [description]
     * @return [type]                [description]
     */
    public function acceptReservation($reservationId)
    {
        $reservation = null;

        DB::transaction(function() use ($reservationId, $reservation) {
            $reservation = ProductReservation::findOrFail($reservationId);
            $reservation->status = ProductReservation::STATUS_ACCEPTED;
            $reservation->save();
        });

        return $reservation;
    }

    /**
     * Decline a Reservation.
     *
     * @param  [type] $reservationId [description]
     * @return [type]                [description]
     */
    public function declineReservation($reservationId)
    {
        $reservation = null;

        DB::transaction(function() use ($reservationId, $reservation) {
            $reservation = ProductReservation::findOrFail($reservationId);
            $reservation->status = ProductReservation::STATUS_DECLINED;
            $reservation->save();
        });

        return $reservation;
    }
}