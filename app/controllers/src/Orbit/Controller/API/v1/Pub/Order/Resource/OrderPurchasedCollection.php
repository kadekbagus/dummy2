<?php

namespace Orbit\Controller\API\v1\Pub\Order\Resource;

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
                $this->data['records'][$item->order_id] = [
                    'order_id' => $item->order_id,
                    'order_status' => $item->order_status,
                    'order_amount' => 0,
                    'payment_transaction_id' => $item->payment_transaction_id,
                    'payment_status' => $item->payment_status,
                    'store_id' => $item->store_id,
                    'store_name' => $item->store_name,
                    'floor' => $item->floor,
                    'unit' => $item->unit,
                    'mall_id' => $item->mall_id,
                    'mall_name' => $item->mall_name,
                    'image_url' => $item->store_image_url,
                    'items' => [],
                ];
            }

            if (! empty($item->brand_product_variant_id)) {
                $this->transformBrandProductOrderItem($item);
            }
        }

        // $this->data['returned_records'] = count($this->data['records']['items']);
        // $this->data['records']['items'] = array_values($this->data['records']['items']);
        // $this->data['records']['stores'] = array_values($this->data['records']['stores']);
        // $this->data['records']['orders'] = array_values($this->data['records']['orders']);
        $this->data['records'] = array_values($this->data['records']);

        return $this->data;
    }

    private function transformBrandProductOrderItem($item)
    {
        $this->data['records'][$item->order_id]['order_amount'] += $item->quantity * $item->selling_price;
        $this->data['records'][$item->order_id]['items'][] = [
            // 'store_id' => $item->store_id,
            // 'order_id' => $item->order_id,
            // 'order_detail_id' => $item->order_detail_id,
            'quantity' => $item->quantity,
            'original_price' => $item->original_price,
            'selling_price' => $item->selling_price,
            'brand_id' => $item->brand_id,
            'product_type' => 'brand_product',
            'product_id' => $item->brand_product_id,
            'product_name' => $item->product_name,
            'product_status' => $item->product_status,
            'variant' => $item->variant,
            'image_url' => $item->image_url,
        ];
    }
}
