<?php

namespace Orbit\Controller\API\v1\BrandProduct\Repository;

use BrandProductVariant;
use BrandProductReservation;
use BrandProductReservationDetail;
use BrandProductReservationVariantDetail;
use Carbon\Carbon;
use CartItem;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Request;
use Orbit\Controller\API\v1\Reservation\ReservationInterface;
use Orbit\Helper\Cart\CartInterface;

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
        if (is_string($reservationData->object_id)) {
            Request::replace(['object_id' => [$reservationData->object_id]]);
        }

        return $this->makeMultiple($reservationData);
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
                'brand_product_variant.brand_product.brand_product_main_photo',
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

        App::make(CartInterface::class)->removeItem($reservationsData->object_id);

        DB::commit();

        Event::fire('orbit.reservation.made_multiple', [$reservations]);

        return array_values($reservations);
    }

    /**
     * Create a reservation for each store.
     *
     * @param array $reservations   the reservation list
     * @param User  $customer       the customer object
     * @param array $storeData      reservation data for the given store
     *
     * @return void
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

            $imgPath = null;
            $cdnUrl = null;
            if (! empty($product->brand_product_main_photo)) {
                if (is_object($product->brand_product_main_photo[0])) {
                    $imgPath = $product->brand_product_main_photo[0]->path;
                    $cdnUrl = $product->brand_product_main_photo[0]->cdn_url;
                }
            }

            $details[] = new BrandProductReservationDetail([
                    'product_name' => $product->product_name,
                    'product_code' => $variant->product_code,
                    'brand_product_variant_id' => $variantId,
                    'brand_product_id' => $product->brand_product_id,
                    'sku' => $variant->sku,
                    'original_price' => $variant->original_price,
                    'selling_price' => $variant->selling_price,
                    'quantity' => $cartItem->quantity,
                    'image_url' => $imgPath,
                    'image_cdn' => $cdnUrl,
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

            // update stock if previous status is accepted
            if ($reservation->status === BrandProductReservation::STATUS_ACCEPTED) {
                $reservation->load(['details']);
                foreach ($reservation->details as $detail) {
                    $detail->load(['product_variant']);
                    $updateStock = BrandProductVariant::where('brand_product_variant_id', '=', $detail->brand_product_variant_id)->first();
                    if ($updateStock) {
                        $updateStock->quantity = $detail->product_variant->quantity + $detail->quantity;
                        $updateStock->save();
                    }
                }
            }

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
