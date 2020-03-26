<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Resource;

use Config;
use Orbit\Helper\Resource\ResourceCollection;
use Orbit\Helper\Util\CdnUrlGeneratorWithCloudfront;
use Str;

/**
 * Brand Product collection class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductCollection extends ResourceCollection
{
    private $imgUrlHelper = null;

    public function toArray()
    {
        $cdnConfig = Config::get('orbit.cdn');
        $this->imgUrlHelper = CdnUrlGeneratorWithCloudfront::create(
            ['cdn' => $cdnConfig], 'cdn'
        );

        foreach($this->collection as $item) {
            $data = $item['_source'];
            $this->data['records'][] = [
                'id' => $item['_id'],
                'name' => $data['product_name'],
                'slug' => Str::slug($data['product_name']),
                'lowestPrice' => $data['lowest_selling_price'],
                'highestPrice' => $data['highest_selling_price'],
                'lowestOriginalPrice' => $data['lowest_original_price'],
                'highestOriginalPrice' => $data['highest_original_price'],
                'status' => $data['status'],
                'rating' => $this->getRating($data),
                'brandId' => $data['brand_id'],
                'brandName' => $data['brand_name'],
                'image' => $this->transformImages($data),
                'stores' => $this->transformStores($data),
            ];
        }

        return $this->data;
    }

    protected function getRating($item)
    {
        return isset($item['rating']) ? $item['rating'] : 0;
    }

    protected function transformImages($item, $imagePrefix = '')
    {
        $images = '';

        $localPath = $item['image_path'] ?: '';
        $cdnPath = $item['image_cdn'] ?: '';

        $images = $this->imgUrlHelper->getImageUrl($localPath, $cdnPath);

        return $images;
    }

    protected function transformStores($item)
    {
        return $item['link_to_stores'];
    }

    protected function transformPrice($item)
    {
        // $discount = $item->original_price - $item->selling_price;
        // $discount = round($discount / $item->original_price, 2) * 100;
        // $discount = number_format($discount, 2, ',', '.');

        // return [
        //     'discount' => $discount,
        //     'sellingPrice' => $item->selling_price,
        //     'originalPrice' => $item->original_price,
        // ];
    }
}
