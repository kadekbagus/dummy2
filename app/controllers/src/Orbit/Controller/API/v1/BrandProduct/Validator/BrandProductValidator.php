<?php

namespace Orbit\Controller\API\v1\BrandProduct\Validator;

use App;
use BrandProduct;
use BrandProductVariant;
use BrandProductReservation;
use CartItem;
use Orbit\Helper\Resource\MediaQuery;
use Order;

/**
 * Brand Product Validator.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductValidator
{
    use MediaQuery;

    protected $imagePrefix = null;

    public function exists($attribute, $productId, $params, $validator)
    {
        $brandProduct = BrandProduct::with(['creator'])
            ->where('brand_product_id', $productId)
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

        $usedQuantity = BrandProductReservation::getReservedQuantity($variant->brand_product_variant_id);

        $usedQuantity += Order::getPurchasedQuantity($variant->brand_product_variant_id);

        return $variant->quantity - $usedQuantity >= $value;
    }

    public function reservationExists($attrs, $value, $params)
    {
        $this->setupImageUrlQuery();

        $reservation = BrandProductReservation::with([
            'users',
            'store.mall',
            'details.variant_details',
            'details.product_variant.brand_product' => function($query) {
                $this->imagePrefix = 'brand_product_main_photo_';
                $query->with($this->buildMediaQuery());
            },
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
        return $this->reservationStatusCanBeChanged($attrs, $value, ['cancel']);
    }

    public function reservationStatusCanBeChanged($attrs, $value, $params)
    {
        if (! App::bound('reservation')) {
            return false;
        }

        $reservation = App::make('reservation');

        if (! isset($params[0])) {
            $params[0] = $value;
        }

        switch ($params[0]) {
            case 'cancel':
                return $this->reservationCanBeCancelled($reservation);
                break;
            case BrandProductReservation::STATUS_PICKED_UP:
                return $this->reservationCanBePickedUp($reservation);
                break;
            case BrandProductReservation::STATUS_ACCEPTED:
                return $this->reservationCanBeAccepted($reservation);
                break;
            case BrandProductReservation::STATUS_DECLINED:
                return $this->reservationCanBeDeclined($reservation);
                break;
            case BrandProductReservation::STATUS_DONE:
            case BrandProductReservation::STATUS_NOT_DONE:
                return $this->reservationCanBeDoneOrNotDone($reservation);
                break;
            default:
                break;
        }

        return false;
    }

    private function reservationCanBeCancelled($reservation)
    {
        return in_array($reservation->status, [
                BrandProductReservation::STATUS_PENDING,
                BrandProductReservation::STATUS_ACCEPTED,
            ]);
    }

    private function reservationCanBePickedUp($reservation)
    {
        return $reservation->status
            === BrandProductReservation::STATUS_ACCEPTED;
    }

    private function reservationCanBeAccepted($reservation)
    {
        return $reservation->status === BrandProductReservation::STATUS_PENDING;
    }

    private function reservationCanBeDeclined($reservation)
    {
        return in_array($reservation->status, [
            BrandProductReservation::STATUS_PENDING,
            BrandProductReservation::STATUS_ACCEPTED,
        ]);
    }

    private function reservationCanBeDoneOrNotDone($reservation)
    {
        return $reservation->status === BrandProductReservation::STATUS_PICKED_UP;
    }

    public function matchReservationUser($attrs, $value, $params)
    {
        if (! App::bound('reservation')) {
            return false;
        }

        return App::make('reservation')->user_id
            === App::make('currentUser')->user_id;
    }

    /**
     * Determine if pickup location is valid (linked to the product) or not.
     *
     * @param  array
     * @param  array
     * @param  array
     * @return bool valid or not.
     */
    public function pickupLocationValid($attrs, $value, $params)
    {
        $variant = $this->getVariant();

        foreach ($variant->variant_options as $option) {
            if ($option->store && $option->option_id === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * This method assumes brand product variant is available inside container.
     */
    public function quantityAvailableForCart($attr, $requestedQty, $params)
    {
        $variant = $this->getVariant();

        // Add in-cart items' count as used quantity.
        $usedQuantity = CartItem::getCartItemQuantity($variant->brand_product_variant_id);

        return $variant->quantity - $usedQuantity >= $requestedQty;
    }

    public function canReserve($attrs, $cartItemIds, $params)
    {
        if (is_string($cartItemIds)) {
            $cartItemIds = [$cartItemIds];
        }

        $cartItems = CartItem::with(['brand_product_variant'])
            ->whereIn('cart_item_id', $cartItemIds)
            ->where('user_id', App::make('currentUser')->user_id)
            ->active()
            ->get();

        $available = 0;
        foreach($cartItems as $cartItem) {
            $variant = $cartItem->brand_product_variant;

            if ($variant
                && $this->validateBrandProductQuantity($variant, $cartItem->quantity)
            ) {
                $available++;
                App::instance('productVariant', $variant);
            }
        }

        return $available > 0 && $available === $cartItems->count();
    }

    public function reservationEnabled($attr, $cartItemIds, $params)
    {
        if (is_string($cartItemIds)) {
            $cartItemIds = [$cartItemIds];
        }

        $cartItem = CartItem::with(['stores'])
            ->whereIn('cart_item_id', $cartItemIds)
            ->where('user_id', App::make('currentUser')->user_id)
            ->active()
            ->first();

        if ($cartItem->stores) {
            return (int) $cartItem->stores->enable_reservation === 1;
        }

        return false;
    }

    private function validateBrandProductQuantity($variant, $requestedQuantity)
    {
        return $variant->quantity >= $requestedQuantity;
    }

    private function getVariant($variantId = '')
    {
        if (App::bound('productVariant')) {
            return App::make('productVariant');
        }

        $variant = BrandProductVariant::with([
            'brand_product.media',
            'variant_options.option.variant',
            'variant_options.store',
        ])->where('brand_product_variant_id', $variantId)->first();

        App::instance('productVariant', $variant);

        return $variant;
    }
}
