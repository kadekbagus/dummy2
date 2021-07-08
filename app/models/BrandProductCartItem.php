<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

/**
 * Brand Product as a CartItem model.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductCartItem extends CartItem
{
    protected $imagePrefix = 'brand_product_photos_';

    /**
     * Implementation detail when adding brand-product to the cart.
     *
     * @param [type] $request [description]
     */
    public function addItem($request)
    {
        DB::beginTransaction();

        $productVariant = App::make('productVariant');
        $customer = App::make('currentUser');
        $cartItem = CartItem::where(
                'brand_product_variant_id', $request->object_id
            )
            ->where('user_id', $customer->user_id)
            ->active()
            ->first();

        // If item is not in the cart, then create new record.
        // Otherwise, update the quantity.
        if (empty($cartItem)) {
            $cartItem = CartItem::create([
                'user_id' => $customer->user_id,
                'brand_product_variant_id' => $request->object_id,
                'brand_id' => $productVariant->brand_product->brand_id,
                'quantity' => $request->quantity,
                'merchant_id' => $request->pickup_location,
                'status' => CartItem::STATUS_ACTIVE,
            ]);
        }
        else {
            $cartItem->increment('quantity', abs($request->quantity));
            $cartItem->save();
        }

        DB::commit();

        // Event::fire('orbit.cart.item-added', [$cartItem]);

        return $cartItem;
    }

    public function updateItem($cartItemId, $request)
    {
        if ($request->quantity == 0) {
            return $this->removeItem([$cartItemId]);
        }

        DB::beginTransaction();

        $cartItem = CartItem::where('cart_item_id', $cartItemId)->firstOrFail();
        $cartItem->quantity = $request->quantity;
        $cartItem->save();

        DB::commit();

        // Event::fire('orbit.cart.item-updated', [$cartItem]);

        return $cartItem;
    }

    public function removeItem($cartItemId)
    {
        if (! is_array($cartItemId)) {
            $cartItemId = [$cartItemId];
        }

        DB::beginTransaction();

        $cartItems = CartItem::whereIn('cart_item_id', $cartItemId)->get();

        foreach($cartItems as $cartItem) {
            $cartItem->status = self::STATUS_INACTIVE;
            $cartItem->save();
        }

        DB::commit();

        // Event::fire('orbit.cart.item-removed', [$cartItem]);

        return $cartItems;
    }
}
