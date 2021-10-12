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
            $storeLogoImageQuery = $this->setupStoreImageUrlQuery();

            $prefix = DB::getTablePrefix();

            $cartItems = CartItem::select(
                    'cart_items.*',
                    DB::raw("
                        {$prefix}brand_product_variants.quantity as product_quantity,
                        {$prefix}brand_product_variants.original_price,
                        {$prefix}brand_product_variants.selling_price,
                        {$prefix}brand_product_variants.brand_product_id,
                        group_concat({$prefix}variant_options.value separator ', ') as variant,
                        {$prefix}brand_products.brand_id,
                        {$prefix}brand_products.product_name,
                        {$prefix}brand_products.status as product_status,
                        {$prefix}merchants.merchant_id as store_id,
                        {$prefix}merchants.name as store_name,
                        {$prefix}merchants.floor,
                        {$prefix}merchants.enable_reservation,
                        {$prefix}merchants.enable_checkout,
                        {$prefix}merchants.unit,
                        mall.merchant_id as mall_id,
                        mall.name as mall_name,
                        {$this->imageQuery},
                        {$storeLogoImageQuery}
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
                ->groupBy('cart_items.brand_product_variant_id')
                ->orderBy('cart_items.created_at', 'desc');

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

            $cartItems->leftJoin(
                'media as storeMedia',
                'brand_products.brand_id',
                '=',
                DB::raw('storeMedia.object_id')
            );

            $cartItems->whereIn('media.media_name_long', ['brand_product_main_photo_desktop_thumb']);
            $cartItems->whereIn(DB::raw('storeMedia.media_name_long'), ['base_merchant_logo_resized_default']);

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

    protected function setupStoreImageUrlQuery()
    {
        if ($this->mediaUsingCdn) {
            return "CASE WHEN storeMedia.cdn_url IS NULL THEN CONCAT({$this->quote($this->mediaUrlPrefix)}, storeMedia.path) ELSE storeMedia.cdn_url END as store_image_url";
        }

        return "CONCAT({$this->quote($this->mediaUrlPrefix)}, storeMedia.path) as store_image_url";
    }
}
