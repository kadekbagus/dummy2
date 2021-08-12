<?php

namespace Orbit\Controller\API\v1\Pub\Order;

use Exception;
use Illuminate\Support\Facades\DB;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Order\Request\OrderPurchasedListRequest;
use Orbit\Controller\API\v1\Pub\Order\Resource\OrderPurchasedCollection;
use Orbit\Helper\Resource\MediaQuery;
use Order;
use PaymentTransaction;

/**
 * Order purchased list.
 *
 * @author Budi <budi@gotomalls.com>
 */
class OrderPurchasedListAPIController extends PubControllerAPI
{
    use MediaQuery;

    public function handle(OrderPurchasedListRequest $request)
    {
        try {
            $this->setupImageUrlQuery();
            $storeLogoImageQuery = $this->setupStoreImageUrlQuery();
            $prefix = DB::getTablePrefix();

            $purchases = Order::select(
                    DB::raw("
                        {$prefix}orders.order_id,
                        {$prefix}orders.user_id,
                        {$prefix}orders.status as order_status,
                        {$prefix}order_details.order_detail_id,
                        {$prefix}order_details.brand_product_variant_id,
                        {$prefix}order_details.quantity,
                        {$prefix}payment_transactions.payment_transaction_id,
                        {$prefix}payment_transactions.status as payment_status,
                        {$prefix}payment_transactions.created_at as transaction_time,
                        GROUP_CONCAT({$prefix}order_variant_details.value separator ', ') as variant,
                        {$prefix}order_details.original_price,
                        {$prefix}order_details.selling_price,
                        {$prefix}brand_product_variants.brand_product_id,
                        {$prefix}brand_products.brand_id,
                        {$prefix}brand_products.product_name,
                        {$prefix}brand_products.status as product_status,
                        {$prefix}merchants.merchant_id as store_id,
                        {$prefix}merchants.name as store_name,
                        {$prefix}merchants.floor,
                        {$prefix}merchants.unit,
                        mall.merchant_id as mall_id,
                        mall.name as mall_name,
                        {$this->imageQuery},
                        {$storeLogoImageQuery}
                    ")
                )
                ->join('payment_transaction_details', 'orders.order_id', '=', 'payment_transaction_details.object_id')
                ->join('payment_transactions', 'payment_transaction_details.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                ->join('order_details', 'orders.order_id', '=', 'order_details.order_id')
                ->join('order_variant_details', 'order_details.order_detail_id', '=', 'order_variant_details.order_detail_id')
                ->join('brand_product_variants', 'order_details.brand_product_variant_id', '=', 'brand_product_variants.brand_product_variant_id')
                ->join('brand_products', 'brand_product_variants.brand_product_id', '=', 'brand_products.brand_product_id')
                ->join('merchants',
                    'orders.merchant_id',
                    '=',
                    'merchants.merchant_id'
                )
                ->join('merchants as mall',
                    'merchants.parent_id',
                    '=',
                    DB::raw('mall.merchant_id')
                )
                ->where('orders.user_id', $request->user()->user_id)
                ->where('orders.status', '<>', Order::STATUS_PENDING)
                ->where('payment_transactions.status', '<>', PaymentTransaction::STATUS_STARTING)
                ->where('payment_transaction_details.object_type', 'order')
                ->groupBy('orders.order_id')
                ->groupBy('order_details.order_detail_id');

            $purchasesCount = clone $purchases;

            $purchases->leftJoin(
                'media',
                'brand_products.brand_product_id',
                '=',
                'media.object_id'
            );

            $purchases->leftJoin(
                'media as storeMedia',
                'brand_products.brand_id',
                '=',
                DB::raw('storeMedia.object_id')
            );

            $purchases->whereIn('media.media_name_long', ['brand_product_main_photo_desktop_thumb']);
            $purchases->whereIn(DB::raw('storeMedia.media_name_long'), ['base_merchant_logo_resized_default']);

            $purchases->skip($request->skip)->take($request->getTake());

            $this->response->data = new OrderPurchasedCollection(
                $purchases->get(),
                $purchasesCount->count()
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }

    private function setupStoreImageUrlQuery()
    {
        if ($this->mediaUsingCdn) {
            return "CASE WHEN storeMedia.cdn_url IS NULL THEN CONCAT({$this->quote($this->mediaUrlPrefix)}, storeMedia.path) ELSE storeMedia.cdn_url END as store_image_url";
        }

        return "CONCAT({$this->quote($this->mediaUrlPrefix)}, storeMedia.path) as store_image_url";
    }
}
