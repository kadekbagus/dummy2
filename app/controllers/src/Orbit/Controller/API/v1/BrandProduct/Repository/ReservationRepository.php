<?php

namespace Orbit\Controller\API\v1\BrandProduct\Repository;

use BrandProductReservation;
use BrandProductReservationDetail;
use BrandProductReservationVariantDetail;
use Carbon\Carbon;
use CartItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Orbit\Controller\API\v1\Reservation\ReservationInterface;

/**
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReservationRepository implements ReservationInterface
{
    protected $reservation = null;

    public function get($id)
    {
        return $this->reservation = BrandProductReservation::onWriteConnection()
            // enable later when needed.
            // ->with([
            //     'users',
            //     'details',
            // ])
            ->where('brand_product_reservation_id', $id)
            ->first();
    }

    /**
     * @param BrandProductVariant $item
     * @param Orbit\Helper\Request\ValidateRequest $reservationData
     */
    public function make($item, $reservationData)
    {
        if (is_array($reservationData->object_id)) {
            return $this->makeMultiple($reservationData);
        }

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
        $reservation->status = BrandProductReservation::STATUS_PENDING;
        $reservation->brand_id = $item->brand_product->brand_id;
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

        // Save image, so that when a variant that linked to current reservation
        // deleted or changed, we still be able to get the image.
        foreach($item->brand_product->media as $media) {
            if (stripos($media->media_name_long, 'orig') !== false) {
                $reservationDetails = new BrandProductReservationDetail;
                $reservationDetails->option_type = 'image';
                $reservationDetails->value = $media->media_id;
                $reservation->details()->save($reservationDetails);
            }
        }

        DB::commit();

        // fire event?
        Event::fire('orbit.reservation.made', [$reservation]);

        return $reservation;
    }

    /**
     * Make multiple reservations (for each store).
     *
     * @param  [type] $items           [description]
     * @param  [type] $reservationData [description]
     * @return [type]                  [description]
     */
    public function makeMultiple($reservationsData)
    {
        $reservations = [];

        DB::beginTransaction();

        $cartItems = CartItem::with([
                'brand_product_variant.brand_product',
                'brand_product_variant.variant_options' => function($query) {
                    $query->where('option_type', 'variant_option')
                        ->with(['option.variant']);
                },
            ])
            ->whereIn('cart_item_id', $reservationsData->object_id)
            ->active()
            ->get();

        $cartItemsByStore = [];
        $cartItems->each(function($cartItem) use (&$cartItemsByStore) {
            $storeId = $cartItem->merchant_id;
            if (! isset($cartItemsByStore[$storeId])) {
                $cartItemsByStore[$storeId] = [
                    'brand_id' => $cartItem->brand_id,
                    'store_id' => $cartItem->merchant_id,
                    'total_amount' => 0,
                    'items' => [],
                ];
            }

            $cartItemsByStore[$storeId]['items'][] = $cartItem;
            $cartItemsByStore[$storeId]['total_amount'] += $cartItem->quantity
                * $cartItem->brand_product_variant->selling_price;
        });

        foreach($cartItemsByStore as $storeId => $storeData) {
            $this->createReservationsByStore(
                $reservations,
                $reservationsData->user(),
                $storeData
            );
        }

        DB::commit();

        // Event::fire('orbit.reservation.made_multiple', [$reservations]);

        return array_values($reservations);
    }

    /**
     * Create a reservation for each store.
     *
     * @param array $reservations   the reservation list
     * @param User  $customer       the customer object
     * @param array $storeData      reservation data for the given store
     *
     * @return array $reservations  updated reservation list
     */
    private function createReservationsByStore(&$reservations, $customer, $storeData)
    {
        $storeId = $storeData['store_id'];
        $reservations[$storeId] = new BrandProductReservation;
        $reservations[$storeId]->user_id = $customer->user_id;
        $reservations[$storeId]->brand_id = $storeData['brand_id'];
        $reservations[$storeId]->merchant_id = $storeId;
        $reservations[$storeId]->total_amount = $storeData['total_amount'];
        $reservations[$storeId]->status = BrandProductReservation::STATUS_PENDING;
        $reservations[$storeId]->save();

        $details = [];
        $reservationVariantDetails = [];
        foreach($storeData['items'] as $cartItem) {
            $variant = $cartItem->brand_product_variant;
            $product = $cartItem->brand_product_variant->brand_product;
            $variantId = $variant->brand_product_variant_id;

            $details[] = new BrandProductReservationDetail([
                    'product_name' => $product->product_name,
                    'product_code' => $variant->product_code,
                    'brand_product_variant_id' => $variantId,
                    'sku' => $variant->sku,
                    'original_price' => $variant->original_price,
                    'selling_price' => $variant->selling_price,
                    'quantity' => $cartItem->quantity,
                ]);

            foreach($variant->variant_options as $variantOption) {
                $reservationVariantDetails[$variantId][] = new BrandProductReservationVariantDetail([
                    'option_type' => $variantOption->option_type,
                    'option_id' => $variantOption->option_id,
                    'value' => $variantOption->option->value,
                    'variant_id' => $variantOption->option->variant_id,
                    'variant_name' => $variantOption->option->variant->variant_name,
                ]);
            }
        }

        if (count($details) > 0) {
            $reservations[$storeId]->details()->saveMany($details);

            foreach($reservations[$storeId]->details as $reservationDetail) {
                if (isset($reservationVariantDetails[$reservationDetail->brand_product_variant_id])) {
                    $reservationDetail->variant_details()->saveMany(
                        $reservationVariantDetails[$reservationDetail->brand_product_variant_id]
                    );
                }
            }
        }
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

            Event::fire('orbit.reservation.canceled', [$reservation]);
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
        $reservation->status = BrandProductReservation::STATUS_EXPIRED;
        $reservation->save();

        return $reservation;
    }

    public function done($reservation)
    {
        $reservation->status = BrandProductReservation::STATUS_DONE;
        $reservation->save();

        return $reservation;
    }

    public function accepted($reservation)
    {
        return $reservation->status === BrandProductReservation::STATUS_ACCEPTED;
    }
}
