<?php

namespace Orbit\Controller\API\v1\Pub\Cart;

use CartItem;
use Illuminate\Support\Facades\DB;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Cart\Request\CartItemListRequest;
use Orbit\Controller\API\v1\Pub\Cart\Resource\CartItemCollection;
use Orbit\Helper\Resource\MediaQuery;

/**
 * Cart item list controller.
 *
 * @author Budi <budi@gotomalls.com>
 */
class CartItemListAPIController extends PubControllerAPI
{
    use MediaQuery;

    public function handle(CartItemListRequest $request)
    {
        try {
            $this->setupImageUrlQuery();

            $prefix = DB::getTablePrefix();

            $cartItems = CartItem::select(
                    'cart_items.*',
                    DB::raw("
                        (
                            select sum({$prefix}order_details.quantity) as purchased_quantity
                            from {$prefix}orders
                            join {$prefix}order_details on {$prefix}orders.order_id = {$prefix}order_details.order_id
                            where {$prefix}orders.status = 'paid'
                            and {$prefix}order_details.brand_product_variant_id = {$prefix}cart_items.brand_product_variant_id
                        ) as purchased_quantity,
                        (
                            select sum({$prefix}brand_product_reservations.quantity) as reserved_quantity
                            from {$prefix}brand_product_reservations
                            where {$prefix}brand_product_reservations.status in ('pending', 'accepted', 'done')
                            and {$prefix}brand_product_reservations.brand_product_variant_id = {$prefix}cart_items.brand_product_variant_id
                        ) as reserved_quantity,
                        {$prefix}brand_product_variants.quantity as product_quantity,
                        {$prefix}brand_product_variants.original_price,
                        {$prefix}brand_product_variants.selling_price,
                        {$prefix}brand_product_variants.brand_product_id,
                        group_concat({$prefix}variant_options.value separator ', ') as variant,
                        {$prefix}brand_products.product_name,
                        {$prefix}brand_products.status as product_status,
                        {$prefix}merchants.merchant_id as store_id,
                        {$prefix}merchants.name as store_name,
                        {$prefix}merchants.floor,
                        {$prefix}merchants.unit,
                        mall.merchant_id as mall_id,
                        mall.name as mall_name,
                        {$this->imageQuery}
                    ")
                )
                ->leftJoin('brand_product_variants',
                    'cart_items.brand_product_variant_id',
                    '=',
                    'brand_product_variants.brand_product_variant_id'
                )
                ->leftJoin('brand_products',
                    'brand_product_variants.brand_product_id',
                    '=',
                    'brand_products.brand_product_id'
                )
                ->join('brand_product_variant_options',
                    'cart_items.brand_product_variant_id',
                    '=',
                    'brand_product_variant_options.brand_product_variant_id'
                )
                ->join('variant_options',
                    'brand_product_variant_options.option_id',
                    '=',
                    'variant_options.variant_option_id'
                )
                ->leftJoin('merchants',
                    'cart_items.merchant_id',
                    '=',
                    'merchants.merchant_id'
                )
                ->leftJoin('merchants as mall',
                    'merchants.parent_id',
                    '=',
                    DB::raw('mall.merchant_id')
                )
                ->where('cart_items.user_id', $request->user()->user_id)
                ->where('cart_items.status', CartItem::STATUS_ACTIVE)
                ->where('brand_products.status', 'active')
                ->where('merchants.status', 'active')
                ->where(DB::raw('mall.status'), 'active')
                ->where('brand_product_variant_options.option_type', 'variant_option')
                ->groupBy('cart_item_id')
                ->groupBy('cart_items.brand_product_variant_id');

            if ($request->has('merchant_id')) {
                $cartItems->where('merchants.merchant_id', $request->merchant_id);
            }

            // Clone cart items without skip and take limit for pagination.
            $totalCartItems = clone $cartItems;

            $cartItems->leftJoin(
                'media',
                'brand_products.brand_product_id',
                '=',
                'media.object_id'
            );

            $cartItems->whereIn('media_name_long', ['brand_product_main_photo_desktop_thumb']);

            if ($request->has('skip')) {
                $cartItems->skip($request->skip);
            }

            if ($request->has('take')) {
                $cartItems->take($request->getTake());
            }

            $this->response->data = new CartItemCollection(
                $cartItems->get(),
                $totalCartItems->count()
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
