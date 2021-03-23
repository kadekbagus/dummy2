<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Resource;

use Orbit\Helper\Resource\Resource;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
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
    private $storeIds = [];

    public function toArray()
    {
        $variantOptions = VariantOption::with(['variant'])->get();
        $arrVariants = [];
        foreach($variantOptions as $vo) {
            $arrVariants[$vo->variant_option_id] = [
                'name' => $vo->variant->variant_name,
                'value' => $vo->value,
            ];
        }

        return [
            'id' => $this->brand_product_id,
            'name' => $this->product_name,
            'description' => $this->product_description,
            'tnc' => $this->tnc,
            'status' => $this->status,
            'maxReservationTime' => ceil($this->max_reservation_time/60),
            'category' => $this->getCategory($this->resource),
            'brandId' => $this->brand_id,
            'brandName' => $this->brand->name,
            'brandSlugName' => \Str::slug($this->brand->name),
            'mainPhoto' => $this->transformMainPhoto($this->resource),
            'otherPhotos' => $this->transformImages($this->resource),
            'variants' => $this->transformVariants(
                $this->resource,
                $arrVariants
            ),
            'videos' => $this->transformVideos($this->resource),
            'stores' => $this->transformStores(
                $this->resource,
                $arrVariants
            ),
            'category_names' => $this->category_names,
            'marketplaces' => $this->marketplaces,
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
                'quantity' => $variant->quantity - $variant->reservations->sum('quantity'),
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
                if (! in_array($option->option_id, $this->storeIds)) {
                    $this->storeIds[] = $option->option_id;
                }

                continue;
            }

            $optionList[] = [
                'name' => $variantOptions[$option->option_id]['name'],
                'value' => $variantOptions[$option->option_id]['value'],
            ];
        }

        return $optionList;
    }

    protected function transformStores($item, $variantOptions)
    {
        $storeList = [];

        // Fetch store info
        $storeInfo = Tenant::select(
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
                ->whereIn('merchants.merchant_id', $this->storeIds);

        // filter by country
        OrbitInput::get('country', function($country) use ($storeInfo) {
            $country = strip_tags($country);
            $storeInfo->where(DB::raw('mall.country'), $country);
        });

        // filter by city
        OrbitInput::get('city', function($city) use ($storeInfo) {
            $city = (array) $city;
            $storeInfo->whereIn(DB::raw('mall.city'), $city);
        });

        $storeInfo->orderBy(DB::raw('mall.name'), 'asc');
                
        $_storeInfo = clone $storeInfo;

        $linkedStores = $_storeInfo->get();

        // Transform options per store
        foreach($linkedStores as $store) {
            $storeList[$store->store_id] = [
                'store_id' => $store->store_id,
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
                        $bpVariantId = $bpVariant->brand_product_variant_id;
                        $storeList[$variantOption->option_id]['variants']
                            [$bpVariantId] = [
                                'id' => $bpVariantId,
                                'sku' => $bpVariant->sku,
                                'product_code' => $bpVariant->product_code,
                                'original_price' => $bpVariant->original_price,
                                'selling_price' => $bpVariant->selling_price,
                                'quantity' => $bpVariant->quantity - $bpVariant->reservations->sum('quantity'),
                                'options' => $optionList,
                            ];

                        $optionList = [];
                    }
                    else {
                        $optionList[] = [
                            'name' => $variantOptions[$variantOption->option_id]['name'],
                            'value' => $variantOptions[$variantOption->option_id]['value'],
                        ];
                    }
                }
            }
        }

        $storeList = array_map(function($store) {
            $store['variants'] = array_values($store['variants']);
            return $store;
        }, $storeList);

        $storeListRawData = array_values($storeList);

        // remove empty store
        $storeListData = [];
        foreach($storeListRawData as $data) {
            if (isset($data['store_id'])) {
                $storeListData[] = $data;
            }
        }

        return $storeListData;
    }

    protected function transformVideos($item)
    {
        return array_map(function($video) {
            return $video['youtube_id'];
        }, $item->videos->toArray());
    }
}
