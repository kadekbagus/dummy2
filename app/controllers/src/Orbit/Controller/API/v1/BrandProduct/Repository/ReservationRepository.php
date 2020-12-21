<?php

namespace Orbit\Controller\API\v1\BrandProduct\Repository;

use BrandProductReservation;
use BrandProductReservationDetail;
use Illuminate\Support\Facades\DB;
use Orbit\Controller\API\v1\Reservation\ReservationInterface;

/**
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReservationRepository implements ReservationInterface
{
    /**
     * @param BrandProductVariant $item
     * @param Orbit\Helper\Request\ValidateRequest $reservationData
     */
    public function make($item, $reservationData)
    {
        DB::beginTransaction();

        $reservation = new BrandProductReservation;
        // $reservation->brand_product_id = $item->brand_product->brand_product_id;
        $reservation->brand_product_variant_id = $item->brand_product_variant_id;
        $reservation->product_name = $item->brand_product->product_name;
        $reservation->sku = $item->sku;
        $reservation->product_code = $item->product_code;
        $reservation->original_price = $item->original_price;
        $reservation->selling_price = $item->selling_price;
        $reservation->quantity = $reservationData->quantity;
        $reservation->user_id = $reservationData->user()->user_id;
        $reservation->status = BrandProductReservation::STATUS_NEW;
        $reservation->save();

        // foreach($item->variant_options as $variantOptions)  {

        // }

        // $reservationDetails = new BrandProductReservationDetail;
        // $reservationDetails->brand_product_reservation_id = $reservation->brand_product_reservation_id;
        // $reservationDetails->save();

        DB::commit();

        // fire event?
        // Event::fire();

        return $reservation;
    }

    public function accept($reservation)
    {
        DB::transaction(function() use (&$reservation) {
            $reservation->status = BrandProductReservation::STATUS_ACCEPTED;
            $reservation->save();
        });

        return $reservation;
    }

    public function cancel($reservation)
    {
        DB::transaction(function() use (&$reservation) {
            $reservation->status = BrandProductReservation::STATUS_CANCELED;
            $reservation->save();
        });

        return $reservation;
    }

    public function decline($reservation, $reason = 'Out of Stock')
    {
        DB::transaction(function() use ($reservation, $reason) {
            $reservation->status = BrandProductReservation::STATUS_DECLINED;
            $reservation->
            $reservation->save();
        });

        return $reservation;
    }

    public function done($reservation)
    {
        DB::transaction(function() use (&$reservation) {
            $reservation->status = BrandProductReservation::STATUS_DONE;
            $reservation->save();
        });

        return $reservation;
    }
}
