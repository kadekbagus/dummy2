<?php

namespace Orbit\Controller\API\v1\Pub\Order\Resource;

use Carbon\Carbon;
use Orbit\Helper\Resource\ResourceCollection;

/**
 * Order purchased collection.
 *
 * @author Budi <budi@gotomalls.com>
 */
class OrderPurchasedCollection extends ResourceCollection
{
    public function toArray()
    {
        $this->data['records'] = [];

        foreach($this->collection as $item) {

            if (! isset($this->data['records'][$item->order_id])) {
                $storeImageUrl = $item->store->media->count() > 0
                    ? $item->store->media->first()->image_url
                    : null;

                $this->data['records'][$item->order_id] = [
                    'order_id' => $item->order_id,
                    'order_status' => $item->status,
                    'order_amount' => $item->total_amount,
                    'payment_transaction_id' => $item->payment_detail->payment_transaction_id,
                    'payment_status' => $item->payment_detail->payment->status,
                    'transaction_time' => $item->created_at
                        ->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
                    'store_id' => $item->store->merchant_id,
                    'store_name' => $item->store->name,
                    'floor' => $item->store->floor,
                    'unit' => $item->store->unit,
                    'mall_id' => $item->store->mall->merchant_id,
                    'mall_name' => $item->store->mall->name,
                    'image_url' => $storeImageUrl,
                    'items' => [],
                ];
            }

            if (! empty($item->brand_id)) {
                $this->transformBrandProductOrderItem($item);
            }
        }

        // $this->data['records']['items'] = array_values($this->data['records']['items']);
        // $this->data['records']['stores'] = array_values($this->data['records']['stores']);
        // $this->data['records']['orders'] = array_values($this->data['records']['orders']);
        $this->data['records'] = array_values($this->data['records']);
        $this->data['returned_records'] = count($this->data['records']);

        return $this->data;
    }

    private function transformBrandProductOrderItem($item)
    {
        foreach($item->details as $detail) {
            $product = $detail->brand_product_variant->brand_product;
            $imageUrl = $product->media->count() > 0
                ? $product->media->first()->image_url
                : null;

            $this->data['records'][$item->order_id]['items'][] = [
                // 'store_id' => $item->store_id,
                // 'order_id' => $item->order_id,
                // 'order_detail_id' => $item->order_detail_id,
                'quantity' => $detail->quantity,
                'original_price' => $detail->original_price,
                'selling_price' => $detail->selling_price,
                'brand_id' => $item->brand_id,
                'product_type' => 'brand_product',
                'product_id' => $product->brand_product_id,
                'product_name' => $product->product_name,
                'product_status' => $product->status,
                'variant' => $detail->order_variant_details->implode('value', ', '),
                'image_url' => $imageUrl,
            ];
        }
    }
}
