<?php

use Orbit\Helper\Resource\MediaQuery;

/**
 * Cart Item model.
 *
 * @author Budi <budi@gotomalls.com>
 */
class CartItem extends Eloquent
{
    use ModelStatusTrait,
        MediaQuery;

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'deleted';

    protected $guarded = [];

    protected $primaryKey = 'cart_item_id';

    protected $table = 'cart_items';

    public function brand_product_variant()
    {
        return $this->belongsTo(BrandProductVariant::class);
    }

    public function updateItem($cartItemId, $request)
    {
        if ($request->quantity == 0) {
            return $this->removeItem([$cartItemId]);
        }

        $cartItem = CartItem::where('cart_item_id', $cartItemId)->firstOrFail();
        $cartItem->quantity = $request->quantity;
        $cartItem->save();

        // Event::fire('orbit.cart.item-updated', [$cartItem]);

        return $cartItem;
    }

    /**
     * remove item(s) from cart.
     *
     * @param  array|string $cartItemId array of cart item id
     * @return self|Collection a collection of removed item(s)
     */
    public function removeItem($cartItemId)
    {
        if (is_string($cartItemId)) {
            $cartItemId = explode(',', $cartItemId);
        }

        if (! is_array($cartItemId)) {
            $cartItemId = [$cartItemId];
        }

        $cartItems = CartItem::whereIn('cart_item_id', $cartItemId)->get();

        foreach($cartItems as $cartItem) {
            $cartItem->status = self::STATUS_INACTIVE;
            $cartItem->save();
        }

        // Event::fire('orbit.cart.item-removed', [$cartItem]);

        return $cartItems;
    }

    public static function getCartItemQuantity($variantId)
    {
        return CartItem::select('quantity')
            ->where('user_id', App::make('currentUser')->user_id)
            ->where('brand_product_variant_id', $variantId)
            ->active()
            ->sum('quantity');
    }
}
