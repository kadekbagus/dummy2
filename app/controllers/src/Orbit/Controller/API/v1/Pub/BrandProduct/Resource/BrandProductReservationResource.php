<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Resource;

use Media;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Orbit\Helper\Resource\Resource;
use Orbit\Helper\Resource\MediaQuery;

/**
 * Brand Product reservation resource class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductReservationResource extends Resource
{
    use MediaQuery;

    public function toArray()
    {
        $this->loadUrlConfigAndPrefix();

        return [
            'id' => $this->brand_product_reservation_id,
            'user_email' => $this->resource->users->user_email,
            'store' => $this->getStore(),
            'reservation_time' => $this->created_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'expiration_time' => Carbon::parse($this->expired_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'cancel_time' => $this->updated_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'decline_time' => $this->updated_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'total_payment' => $this->resource->total_amount,
            'status' => $this->status,
            'decline_reason' => $this->cancel_reason,
            'items' => $this->transformItems(),
        ];
    }

    protected function transformImages($item, $imagePrefix = '')
    {
        if ($this->mediaUsingCdn) {
            return $item->image_cdn
                ? $item->image_cdn
                : $this->mediaUrlPrefix . $item->image_url;
        }

        return $this->mediaUrlPrefix . $item->image_url;
    }

    private function getStore()
    {
        $store = $this->resource->store;
        return [
            'store_id' => $store->merchant_id,
            'store_name' => $store->name,
            'floor' => $store->floor,
            'unit' => $store->unit,
            'mall_id' => $store->mall->merchant_id,
            'mall_name' => $store->mall->name,
        ];
    }

    private function getVariant()
    {
        return $this->resource->details->implode('value', ', ');
    }

    private function transformItems()
    {
        $items = [];

        foreach($this->resource->details as $detail) {
            $items[] = [
                'product_id' => $detail->brand_product_id,
                'variant_id' => $detail->brand_product_variant_id,
                'product_name' => $detail->product_name,
                'barcode' => $detail->product_code,
                'sku' => $detail->sku,
                'quantity' => $detail->quantity,
                'original_price' => $detail->original_price,
                'selling_price' => $detail->selling_price,
                'variant' => $detail->variant_details->implode('value', ', '),
                'images' => $this->transformImages($detail, 'brand_product_main_photo_'),
            ];
        }

        return $items;
    }
}
