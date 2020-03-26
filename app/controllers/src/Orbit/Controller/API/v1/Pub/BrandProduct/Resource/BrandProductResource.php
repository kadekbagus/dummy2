<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Resource;

use Orbit\Helper\Resource\Resource;
use VariantOption;

/**
 * Brand Product resource class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductResource extends Resource
{
    public function toArray()
    {
        $variantOptions = VariantOption::with(['variant'])->get();

        return [
            'id' => $this->brand_product_id,
            'name' => $this->product_name,
            'description' => $this->product_description,
            'tnc' => $this->tnc,
            'status' => $this->status,
            'maxReservationTime' => $this->max_reservation_time,
            'brandId' => $this->brand_id,
            'brandName' => $this->brand->name,
            'brandSlugName' => \Str::slug($this->brand->name),
            'mainPhoto' => $this->transformMainPhoto($this->resource),
            'otherPhotos' => $this->transformImages($this->resource),
            'variants' => $this->transformVariants(
                $this->resource,
                $variantOptions
            ),
            'videos' => $this->transformVideos($this->resource),
        ];
    }

    protected function transformMainPhoto($item)
    {
        return $item->brand_product_main_photo;
    }

    protected function transformImages($item, $imagePrefix = '')
    {
        return $item->brand_product_photos;
    }

    protected function transformVariants($item, $variantOptions)
    {
        $variants = [];

        foreach($item->brand_product_variants as $variant) {
            $discount = $variant->original_price - $variant->selling_price;
            $discount = round($discount / $variant->original_price, 2) * 100;

            $variants[] = [
                'sku' => $variant->sku,
                'productCode' => $variant->product_code,
                'originalPrice' => $variant->original_price,
                'sellingPrice' => $variant->selling_price,
                'discount' => $discount,
                'quantity' => $variant->quantity,
                'options' => $this->transformVariantOptions(
                    $variant->variant_options, $variantOptions
                ),
            ];
        }

        return $variants;
    }

    protected function transformVariantOptions($options, $variantOptions)
    {
        $optionList = [];

        foreach($options as $option) {
            if ($option->option_type === 'merchant') {
                continue;
            }

            foreach($variantOptions as $variantOption) {
                if ($variantOption->variant_option_id === $option->option_id) {
                    $optionList[] = [
                        'name' => $variantOption->variant->variant_name,
                        'value' => $variantOption->value,
                    ];

                    break;
                }
            }
        }

        return $optionList;
    }

    protected function transformVideos($item)
    {
        return array_map(function($video) {
            return $video['youtube_id'];
        }, $item->videos->toArray());
    }
}
