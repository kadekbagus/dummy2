<?php

namespace Orbit\Controller\API\v1\Pub\Order\Resource;

use Orbit\Helper\Resource\Resource;

/**
 * Order purchased resource class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class OrderPurchasedResource extends Resource
{
    public function toArray()
    {
        $this->data = [
            'payment_transaction_id' => $this->payment_transaction_id,
            'external_payment_transaction_id' => $this->external_payment_transaction_id,
            'user_name' => $this->user_name,
            'user_email' => $this->user_email,
            'user_id' => $this->user_id,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'payment_status' => $this->status,
            // 'verification_code' => $this->notes,
            'transaction_time' => $this->created_at->timezone('Asia/Jakarta')
                ->format('Y-m-d H:i:s'),
            'payment_midtrans_info' => $this->midtrans->payment_midtrans_info,
            'items' => $this->transformPurchaseItems(),
        ];

        return $this->data;
    }

    private function transformPurchaseItems()
    {
        $items = [];
        foreach($this->details as $detail) {
            if (empty($detail->order)) {
                continue;
            }

            foreach($detail->order->details as $orderDetail) {
                $order = $detail->order;
                $storeId = $order->merchant_id;
                if (! isset($items[$storeId])) {
                    $items[$storeId] = [
                        'order_id' => $order->order_id,
                        'order_status' => $order->status,
                        'verification_code' => $order->pick_up_code,
                        'store_name' => $order->store->name,
                        'mall_name' => $order->store->mall->name,
                        'floor' => $order->store->floor,
                        'unit' => $order->store->unit,
                        'amount' => $order->total_amount,
                        'store_image_url' => $this->transformImages(
                            $order->store,
                            'retailer_logo_'
                        ),
                        'items' => [],
                    ];
                }

                $items[$storeId]['items'][] = [
                    'product_id' => $orderDetail->brand_product_variant->brand_product_id,
                    'name' => $orderDetail->brand_product_variant->brand_product->product_name,
                    'variant' => $orderDetail->order_variant_details->implode('value', ', '),
                    'quantity' => $orderDetail->quantity,
                    'original_price' => $orderDetail->original_price,
                    'selling_price' => $orderDetail->selling_price,
                    'image_url' => $this->transformImages(
                        $orderDetail->brand_product_variant->brand_product,
                        'brand_product_main_photo_'
                    )
                ];
            }
        }

        return array_values($items);
    }

    protected function transformImages($item, $imagePrefix = '')
    {
        $images = parent::transformImages($item, $imagePrefix);

        if (isset($images['desktop_thumb'])) {
            return $images['desktop_thumb'];
        }

        if (isset($images['orig'])) {
            return $images['orig'];
        }

        return null;
    }
}
