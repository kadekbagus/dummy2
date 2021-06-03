<?php

namespace Orbit\Helper\Cart;

use Orbit\Helper\Request\ValidateRequest;
use Orbit\Helper\Resource\MediaQuery;

/**
 * A class that helps interacting with cart.
 *
 * author
 */
class Cart implements CartInterface
{
    use MediaQuery;
    use BrandProductCartItem;

    protected $user;

    protected $imagePrefix = 'brand_product_photos_';

    public function __construct($user = null)
    {
        $this->user = $user ? $user : App::make('currentUser');
    }

    /**
     * @param ArrayAccess|object $item the item.
     * @param string $itemType the type of cart item.
     */
    public function addItem($item, $itemType = '')
    {
        $item = !is_array($item) ? $item : (object) $item;

        $itemType = !empty($itemType)
            ? $itemType
            : $this->resolveItemType($item);

        if ($itemType === 'brand_product') {
            return $this->addBrandProductItem($item);
        }
    }

    public function updateItem($itemId, $quantity = 0)
    {
        DB::beginTransaction();

        $cartItem = App::bound('cartItem')
            ? App::make('cartItem')
            : CartItem::findOrFail($itemId);

        $cartItem->quantity = $quantity;

        if ($quantity === 0) {
            $cartItem->status = CartItem::STATUS_INACTIVE;
        }

        $cartItem->save();

        DB::commit();

        return $cartItem;
    }

    public function removeItem($itemId)
    {
        DB::beginTransaction();

        $cartItem = CartItem::whereIn('cart_item_id', $itemId)->update([
            'status' => CartItem::STATUS_INACTIVE
        ]);

        DB::commit();

        return $cartItem;
    }

    public function items($request)
    {
        $this->setupImageUrlQuery();

        $cartItems = CartItem::select(
                'cart_items.*',
                DB::raw('bp.product_name as product_name'),
                DB::raw($this->imageQuery)
            )
            ->leftJoin(
                'brand_product_variants bpv',
                'brand_product_variant_id',
                '=',
                'bpv.brand_product_variant_id'
            )
            ->leftJoin(
                'brand_product bp',
                'bpv.brand_product_id', '=', 'bp.brand_product_id'
            )
            ->leftJoin(
                'media', 'bp.brand_product_id', '=', 'media.object_id'
            )
            ->where('user_id', $this->user->user_id)
            ->where('status', CartItem::STATUS_ACTIVE);

        $imageVariants = $this->resolveImageVariants();
        if (! empty($imageVariants))

        if ($request->has('merchant_id')) {
            $cartItems->where('merchant_id', $request->merchant_id);
        }

        if ($request->has('skip')) {
            $cartItems->skip($request->skip);
        }

        if ($request->has('take')) {
            $cartItems->take($request->getTake());
        }

        return $cartItems;
    }
}
