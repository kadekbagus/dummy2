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
            $logos = Config::get('orbit.marketplace_logo', null);
            if (isset($data['marketplace_names']) && ! empty($logos)) {
                foreach($data['marketplace_names'] as &$marketplace) {
                    $marketplace['marketplace_logo'] = null;
                    if (array_key_exists($marketplace['marketplace_name'], $logos)) {
                        $marketplace['marketplace_logo'] = $logos[$marketplace['marketplace_name']];
                    }
                }
            }

            $this->data['records'][] = [
                'id' => $item['_id'],
                'name' => $data['product_name'],
                'slug' => Str::slug($data['product_name']),
                'lowestPrice' => $data['lowest_selling_price'],
                'highestPrice' => $data['highest_selling_price'],
                'lowestOriginalPrice' => $data['lowest_original_price'],
                'highestOriginalPrice' => $data['highest_original_price'],
                'status' => $data['status'],
                'image' => $this->transformImages($data),
                'brands' => $data['link_to_brands'],
                'brandId' => $data['brand_id'],
                'brandName' => $data['brand_name'],
                'marketplaces' => isset($data['marketplace_names']) ? $data['marketplace_names'] : [],
            ];
        }

        return $this->data;
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
