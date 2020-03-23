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
    public function toArray()
    {
        foreach($this->collection as $item) {
            $data = $item['_source'];
            $this->data[] = [
                'id' => $data['brand_product_id'],
                'name' => $data['brand_product_name'],
                'slug' => Str::slug($data['brand_product_name']),
                'lowestPrice' => $data['lowest_selling_price'],
                'highestPrice' => $data['highest_selling_price'],
                'status' => $data['status'],
                'rating' => $this->getRating($data),
                'brandId' => $data['brand_id'],
                'brandName' => $data['brand_name'],
                'image' => $this->transformImages($data),
                'stores' => $this->transfromStores($data),
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

        $cdnConfig = Config::get('orbit.cdn');
        $imgUrl = CdnUrlGeneratorWithCloudfront::create(['cdn' => $cdnConfig], 'cdn');

        $localPath = isset($items['path']) ? $items['path'] : '';
        $cdnPath = isset($items['cdn_url']) ? $items['cdn_url'] : '';

        $images = $imgUrl->getImageUrl($localPath, $cdnPath);

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
