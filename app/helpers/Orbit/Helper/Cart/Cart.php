<?php

namespace Orbit\Helper\Cart;

use BrandProductCartItem;
use Illuminate\Support\Facades\App;

/**
 * A class that helps interacting with cart.
 *
 * author
 */
class Cart implements CartInterface
{
    // Cart item model instance.
    protected $cartItem;

    /**
     * List of handler for each cart item type.
     * @var [type]
     */
    protected $cartItemHandlers = [
        'default' => CartItem::class,
        'brand_product' => BrandProductCartItem::class,
    ];

    /**
     * Load cart item object based on the product type.
     *
     * @param [type] $productType [description]
     * @param [type] $user        [description]
     */
    public function __construct($productType = 'default')
    {
        $this->cartItem = new $this->cartItemHandlers[$productType]();
    }

    /**
     * Add an item to cart.
     *
     * @param ArrayAccess|object - $item the item.
     * @param string $itemType - the type of cart item.
     */
    public function addItem($item)
    {
        return $this->cartItem->addItem($item);
    }

    /**
     * Update a single cart item.
     *
     * @param  [type] $itemId     [description]
     * @param  array  $updateData [description]
     * @return [type]             [description]
     */
    public function updateItem($itemId, $updateData)
    {
        return $this->cartItem->updateItem($itemId, $updateData);
    }

    /**
     * Remove one or more cart item(s).
     *
     * @param  array  $itemId [description]
     * @return [type]         [description]
     */
    public function removeItem($itemId = [])
    {
        return $this->cartItem->removeItem($itemId);
    }

    /**
     * Get all cart items.
     *
     * @todo This should be made generic instead of specific for each type.
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function items($request)
    {
        return $this->cartItem->items($request);
    }

    /**
     * Try calling method in the cartItem instance.
     *
     * @param  string $method [description]
     * @param  array  $args   [description]
     * @return [type]         [description]
     */
    public function __call(string $method, array $args)
    {
        return $this->cartItem->{$method}($args);
    }
}
