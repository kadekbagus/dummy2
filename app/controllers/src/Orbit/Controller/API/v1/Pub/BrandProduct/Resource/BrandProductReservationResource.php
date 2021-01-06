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
                $this->resource,
                'brand_product_main_photo_'
            ),
            'decline_reason' => $this->decline_reason,
        ];
    }

    protected function transformImages($item, $imagePrefix = '')
    {
        if ($item->brand_product_variant) {
            return parent::transformImages($item->brand_product_variant->brand_product, $imagePrefix);
        }
        else if ($item->image && $item->image->media) {
            $this->setupImageUrlQuery();
            $this->imagePrefix = 'brand_product_main_photo_';
            $imageVariants = $this->resolveImageVariants();

            $media = Media::select(
                'media_id',
                'object_id',
                'media_name_long',
                'path',
                DB::raw($this->imageQuery)
            );

            if (! empty($imageVariants)) {
                $media->whereIn('media_name_long', $imageVariants);
            }

            $media = $media->where('object_id', $item->image->media->object_id)
                ->get();

            return parent::transformImages($media, $imagePrefix);
        }

        return null;
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
