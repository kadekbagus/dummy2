<?php

namespace Orbit\Controller\API\v1\Pub\Cart;

use CartItem;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Cart\Request\ListRequest;
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

    protected $imagePrefix = 'brand_product_photos_';

    public function handle(ListRequest $request)
    {
        try {
            $this->setupImageUrlQuery();

            $cartItems = CartItem::select(
                    'cart_items.*',
                    DB::raw("
                        brand_product_variants.original_price,
                        brand_product_variants.selling_price,
                        brand_product_variants.brand_product_id,
                        brand_products.product_name as product_name,
                        {$this->imageQuery}
                    ")
                )
                ->leftJoin(
                    'brand_product_variants brand_product_variants',
                    'brand_product_variant_id',
                    '=',
                    'brand_product_variants.brand_product_variant_id'
                )
                ->leftJoin(
                    'brand_product brand_products',
                    'brand_product_variants.brand_product_id',
                    '=',
                    'brand_products.brand_product_id'
                )
                ->leftJoin(
                    'media',
                    'brand_products.brand_product_id',
                    '=',
                    'media.object_id'
                )
                ->where('user_id', $request->user()->user_id)
                ->where('status', CartItem::STATUS_ACTIVE);


            $imageVariants = $this->resolveImageVariants();

            if (! empty($imageVariants)) {
                $cartItems->whereIn('media_name_long', $imageVariants);
            }

            if ($request->has('merchant_id')) {
                $cartItems->where('merchant_id', $request->merchant_id);
            }

            if ($request->has('skip')) {
                $cartItems->skip($request->skip);
            }

            if ($request->has('take')) {
                $cartItems->take($request->getTake());
            }

            $this->response->data = new CartItemCollection($cartItems->get());

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
