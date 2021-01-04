<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Resource;

use Carbon\Carbon;
use Orbit\Helper\Resource\Resource;

/**
 * Brand Product reservation resource class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductReservationResource extends Resource
{
    public function toArray()
    {
        return [
            'id' => $this->brand_product_reservation_id,
            'user_email' => $this->resource->users->user_email,
            'store' => $this->getStore(),
            'reservation_time' => $this->created_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'expiration_time' => Carbon::parse($this->expired_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'quantity' => $this->quantity,
            'total_payment' => $this->getTotalPayment(),
            'status' => $this->status,
            'product_name' => $this->resource->product_name,
            'variant' => $this->getVariant(),
            'sku' => $this->sku,
            'barcode' => $this->product_code,
            'images' => $this->transformImages(
                $this->resource->brand_product_variant->brand_product,
                'brand_product_main_photo_'
            ),
            'decline_reason' => $this->decline_reason,
        ];
    }

    private function getStore()
    {
        $store = $this->resource->store->store;
        return [
            'store_name' => $store->name,
            'mall_name' => $store->mall->name,
        ];
    }

    private function getTotalPayment()
    {
        return $this->quantity * $this->selling_price;
    }

    private function getVariant()
    {
        return $this->resource->variants->implode('value', ', ');
    }
}
