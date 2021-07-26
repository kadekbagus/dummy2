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
            'verification_code' => $this->notes,
            'payment_midtrans_info' => $this->midtrans->payment_midtrans_info,
            'discount' => $this->discount,
            'discount_code' => $this->discount_code,
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
                $items[] = [
                    'name' => $orderDetail->brand_product_variant->brand_product->product_name,
                    'variant' => $orderDetail->order_variant_details->implode('value', ', '),
                    'quantity' => $orderDetail->quantity,
                    'original_price' => $orderDetail->original_price,
                    'selling_price' => $orderDetail->selling_price,
                    'order_status' => $detail->order->status,
                    'image_url' => $this->transformImages(
                        $orderDetail->brand_product_variant->brand_product,
                        'brand_product_main_photo_'
                    )
                ];
            }
        }

        return $items;
    }
}
