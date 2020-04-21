<?php

namespace Orbit\Controller\API\v1\Pub\Product\Resource;

use Config;
use Orbit\Helper\Resource\ResourceCollection;
use Orbit\Helper\Util\CdnUrlGeneratorWithCloudfront;
use Str;

/**
 * Product Affiliation suggestion collection class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ProductAffiliationSuggestionCollection extends ResourceCollection
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
                'image' => $this->transformImages($data),
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
