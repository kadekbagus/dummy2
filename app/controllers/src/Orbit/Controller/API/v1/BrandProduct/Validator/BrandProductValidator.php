<?php

namespace Orbit\Controller\API\v1\BrandProduct\Validator;

use App;
use BrandProduct;
use BrandProductVariant;
use BrandProductReservation;

/**
 * Brand Product Validator.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductValidator
{
    public function exists($attribute, $productId, $params, $validator)
    {
        $brandProduct = BrandProduct::where('brand_product_id', $productId)
                            ->first();

        if (! empty($brandProduct)) {
            App::instance('brandProduct', $brandProduct);
        }

        return ! empty($brandProduct);
    }

    public function variant_exists($attribute, $variantId, $params)
    {
        $variant = $this->getVariant($variantId);

        return ! empty($variant);
    }

    public function product_exists($attribute, $value, $params)
    {
        $variant = $this->getVariant($value);

        return ! empty($variant) && ! empty($variant->brand_product);
    }

    /**
     * This method assumes brand product variant is available inside container.
     */
    public function quantity_available($attrs, $value, $params)
    {
        $variant = $this->getVariant();

        if ($variant->quantity === 0) {
            return true;
        }

        $usedQuantity = BrandProductReservation::select('quantity')
            ->where('brand_product_variant_id', $variant->brand_product_variant_id)
            ->whereIn('status', [
                BrandProductReservation::STATUS_PENDING,
                BrandProductReservation::STATUS_DONE
            ])
            ->sum('quantity');

        return $variant->quantity - $usedQuantity >= $value;
    }

    public function reservationExists($attrs, $value, $params)
    {
        $reservation = BrandProductReservation::with([
            'store.store.mall',
            'users',
            'variants',
        ])
        ->where('brand_product_reservation_id', $value)
        ->first();

        if (! empty($reservation)) {
            App::instance('reservation', $reservation);
        }

        return ! empty($reservation);
    }

    public function reservationCanBeCanceled($attrs, $value, $params)
    {
        if (! App::bound('reservation')) {
            return false;
        }

        $reservation = App::make('reservation');

        return App::make('currentUser')->user_id === $reservation->user_id
            && $reservation->status === BrandProductReservation::STATUS_PENDING;
    }

    public function matchReservationUser($attrs, $value, $params)
    {
        if (! App::bound('reservation')) {
            return false;
        }

        return App::make('reservation')->user_id
            === App::make('currentUser')->user_id;
    }

    private function getVariant($variantId = '')
    {
        if (App::bound('productVariant')) {
            return App::make('productVariant');
        }

        $variant = BrandProductVariant::with([
            'brand_product',
            'variant_options.option.variant',
            'variant_options.store',
        ])->where('brand_product_variant_id', $variantId)->first();

        App::instance('productVariant', $variant);

        return $variant;
    }
}
