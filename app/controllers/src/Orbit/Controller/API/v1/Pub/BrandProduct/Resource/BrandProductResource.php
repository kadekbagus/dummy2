<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Resource;

use Orbit\Helper\Resource\Resource;
use VariantOption;
use Tenant;
use DB;

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
            'category' => $this->getCategory($this->resource),
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
            'stores' => $this->transformStores(
                $this->resource,
                $variantOptions
            ),
        ];
    }

    public function getCategory($item)
    {
        return ! $item->categories->isEmpty()
            ? $item->categories->first()->category_name
            : '';
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
            $discount = 0;
            if (! empty($variant->original_price)
                && $variant->original_price > 0.0
            ) {
                $discount = $variant->original_price - $variant->selling_price;
                $discount = round($discount / $variant->original_price, 2) * 100;
            }

            $variants[] = [
                'id' => $variant->brand_product_variant_id,
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

    protected function transformStores($item, $variantOptions)
    {
        $storeList = [];

        // Populate list of store ids.
        $storeIds = [];
        foreach($item->brand_product_variants as $bpVariant) {
            foreach($bpVariant->variant_options as $variantOption) {
                if ($variantOption->option_type === 'merchant') {
                    $storeIds[] = $variantOption->option_id;
                }
            }
        }

        // Fetch store info
        $linkedStores = Tenant::select(
                    'merchants.merchant_id as store_id',
                    'merchants.name as store_name',
                    'merchants.floor',
                    'merchants.unit',
                    DB::raw('mall.merchant_id as mall_id'),
                    DB::raw('mall.name as mall_name'),
                    DB::raw('mall.address_line1 as mall_address'),
                    DB::raw('mall.city as mall_city'),
                    DB::raw('mall.province as mall_province'),
                    DB::raw('mall.postal_code as mall_postal_code')
                )
                ->join('merchants as mall', 'merchants.parent_id', '=',
                    DB::raw('mall.merchant_id')
                )
                ->whereIn('merchants.merchant_id', $storeIds)
                ->orderBy(DB::raw('mall.name'), 'asc')
                ->get();

        // Transform options per store
        foreach($linkedStores as $store) {
            $storeList[$store->store_id] = [
                'store_id' => $variantOption->option_id,
                'store_name' => $store->store_name,
                'mall_id' => $store->mall_id,
                'mall_name' => $store->mall_name,
                'floor' => $store->floor,
                'unit' => $store->unit,
                'mall_address' => $store->mall_address,
                'city' => $store->mall_city,
                'province' => $store->mall_province,
                'postal_code' => $store->mall_postal_code,
                'variants' => [],
            ];

            $optionList = [];
            foreach($item->brand_product_variants as $bpVariant) {
                foreach($bpVariant->variant_options as $variantOption) {
                    if ($variantOption->option_type === 'merchant') {
                        $storeList[$variantOption->option_id]['variants'][] = [
                            'id' => $bpVariant->brand_product_variant_id,
                            'sku' => $bpVariant->sku,
                            'product_code' => $bpVariant->product_code,
                            'original_price' => $bpVariant->original_price,
                            'selling_price' => $bpVariant->selling_price,
                            'quantity' => $bpVariant->quantity,
                            'options' => $optionList,
                        ];
                        $optionList = [];
                    }
                    else {
                        // transform variantOptions as array so no loop here,
                        // check with isset() should be enough.
                        foreach($variantOptions as $varOpt) {
                            if ($varOpt->variant_option_id === $variantOption->option_id) {
                                $optionList[] = [
                                    'name' => $varOpt->variant->variant_name,
                                    'value' => $varOpt->value,
                                ];
                            }
                        }
                    }
                }
            }
        }

        return array_values($storeList);
    }

    protected function transformVideos($item)
    {
        return array_map(function($video) {
            return $video['youtube_id'];
        }, $item->videos->toArray());
    }
}
