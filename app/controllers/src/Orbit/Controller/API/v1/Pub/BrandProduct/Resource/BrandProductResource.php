<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Resource;

use Orbit\Helper\Resource\Resource;

/**
 * Brand Product collection class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductResource extends Resource
{
    public function toArray()
    {
        return [
            'id' => $this->brand_product_id,
            'name' => $this->product_name,
            'description' => $this->product_description,
            'tnc' => $this->tnc,
            'status' => $this->status,
            'maxReservationTime' => $this->max_reservation_time,
            'brandId' => $this->brand_id,
            'images' => $this->transformImages($this->resource),
            'variants' => $this->transformVariants(),
        ];
    }

    protected function transformImages($item, $imagePrefix = '')
    {
        return [];
    }

    protected function transformVariants()
    {
        $variants = [];

        foreach($this->resource->brand_product_variants as $variant) {
            $discount = $variant->original_price - $variant->selling_price;
            $discount = round($discount / $variant->original_price, 2) * 100;

            $variants[] = [
                'sku' => $variant->sku,
                'productCode' => $variant->product_code,
                'originalPrice' => $variant->original_price,
                'sellingPrice' => $variant->selling_price,
                'discount' => $discount,
                'quantity' => $variant->quantity,
            ];
        }

        return $variants;
    }
}
