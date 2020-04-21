<?php

namespace Orbit\Controller\API\v1\Pub\Product\Resource;

use Config;
use Orbit\Helper\Resource\ResourceCollection;
use Orbit\Helper\Util\CdnUrlGeneratorWithCloudfront;
use Str;

/**
 * Product Affiliation collection class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ProductAffiliationCollection extends ResourceCollection
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
                'brandId' => $data['brand_id'],
                'brandName' => $data['brand_name'],
                'image' => $this->transformImages($data),
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
}
