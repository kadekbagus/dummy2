<?php

trait BrandProductCartItem
{
    protected function addBrandProductItem($item)
    {
        DB::beginTransaction();

        $brandProduct = App::make('brandProduct');

        $cartItem = new $this->cart;

        $cartItem->user_id = $this->user->user_id;
        $cartItem->brand_product_variant_id = $item->object_id;
        $cartItem->quantity = $item->quantity;
        $cartItem->brand_id = $brandProduct->brand_id;
        $cartItem->merchant_id = $item->pickup_location;
        $cartItem->status = CartItem::STATUS_ACTIVE;

        $cartItem->save();

        DB::commit();
    }
}
