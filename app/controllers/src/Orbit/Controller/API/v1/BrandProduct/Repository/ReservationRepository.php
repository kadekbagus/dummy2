<?php

namespace Orbit\Controller\API\v1\BrandProduct\Repository;

use BrandProductReservation;
use BrandProductReservationDetail;
use Carbon\Carbon;
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
        $reservation->expired_at = Carbon::now()->addHours(
            $item->brand_product->max_expiration_time
        )->format('Y-m-d H:i:s');
        $reservation->status = BrandProductReservation::STATUS_PENDING;
        $reservation->save();

        foreach($item->variant_options as $variantOption)  {

            $reservationDetails = new BrandProductReservationDetail;
            $reservationDetails->option_type = $variantOption->option_type;

            if ($variantOption->option_type === 'merchant') {
                $reservationDetails->value = $variantOption->option_id;
            }
            else {
                $reservationDetails->value = $variantOption->option->value;
                $reservationDetails->variant_id =
                    $variantOption->option->variant_id;
                $reservationDetails->variant_name =
                    $variantOption->option->variant->variant_name;
            }

            $reservation->details()->save($reservationDetails);
        }

        DB::commit();

        // fire event?
        // Event::fire('orbit.reservation.made', [$reservation]);

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

    public function cancel($reservation, $canceledByUserId = '')
    {
        DB::transaction(function() use (&$reservation, $canceledByUserId) {
            $reservation->status = BrandProductReservation::STATUS_CANCELED;
            $reservation->cancelled_by = $canceledByUserId ?:
                App::make('currentUser')->user_id;
            $reservation->save();
        });

        return $reservation;
    }

    public function decline(
        $reservation,
        $reason = 'Out of Stock',
        $declinedByUserId = ''
    ) {
        DB::transaction(function() use (
            $reservation,
            $reason,
            $declinedByUserId
        ) {
            $reservation->status = BrandProductReservation::STATUS_DECLINED;
            // $reservation->decline_reason = $reason;
            // $reservation->declined_by = $declinedByUserId ?:
            //     App::make('currentUser')->user_id;
            $reservation->save();
        });

        return $reservation;
    }

    public function expire($reservation)
    {
        DB::transaction(function() use (&$reservation) {
            $reservation->status = BrandProductReservation::STATUS_EXPIRED;
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
