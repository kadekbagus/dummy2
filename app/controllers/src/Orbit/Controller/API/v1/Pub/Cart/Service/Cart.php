<?php

namespace Orbit\Controller\API\v1\Pub\Cart\Service;

class Cart
{
    /**
     * Add an item to cart.
     *
     * @param Model $user current customer/user
     * @param mixed $item the item instance
     * @param int $quantity the quantity of item
     * @return Model newly created cart item
     */
    public function add($user, $item, $quantity, $pickupLocation)
    {
        DB::beginTransaction();

        $cartItem = new CartItem;

        $cartItem->user_id = $user->user_id;
        $cartItem->brand_product_variant_id = $item->brand_product_variant_id;
        $cartItem->quantity = $quantity;
        $cartItem->brand_id = $item->brand_id;
        $cartItem->merchant_id = $pickupLocation;
        $cartItem->status = CartItem::STATUS_ACTIVE;

        $cartItem->save();

        DB::commit();

        return $cartItem;
    }
}
