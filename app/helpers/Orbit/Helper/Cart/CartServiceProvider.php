<?php

namespace Orbit\Helper\Cart;

use CartItem;
use Request;
use Illuminate\Support\ServiceProvider;
use Orbit\Helper\Cart\CartInterface;

/**
 * Service provider for cart and order feature.
 *
 * @author Budi <budi@gotomalls.com>
 */
class CartServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(CartInterface::class, function($app, $args = [])
        {
            return new Cart($this->resolveCartItemType($args));
        });
    }

    /**
     * Resolve cart item type based on request input.
     *
     * @return string cart item type.
     */
    private function resolveCartItemType($args)
    {
        if (Request::has('object_type')) {
            return Request::input('object_type');
        }
        else if (Request::has('cart_item_id')) {
            $cartItemId = Request::input('cart_item_id', []);
            if (is_string($cartItemId)) {
                $cartItemId = [$cartItemId];
            }

            $cartItem = CartItem::with([
                    'brand_product_variant',
                ])
                ->whereIn('cart_item_id', $cartItemId)
                ->first();

            if (! empty($cartItem->brand_product_variant)) {
                return 'brand_product';
            }
        }
        else if (isset($args['productType'])) {
            return $args['productType'];
        }

        return 'default';
    }
}
