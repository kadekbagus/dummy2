<?php

namespace Orbit\Controller\API\v1\Pub\Product\Resource;

use Config;
use Orbit\Helper\Resource\ResourceCollection;
use Orbit\Helper\Util\CdnUrlGeneratorWithCloudfront;
use Str;

/**
 * Brand with Product affiliation collection class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandWithProductAffiliationCollection extends ResourceCollection
{
    private $imgUrlHelper = null;

    public function toArray()
    {
        $cdnConfig = Config::get('orbit.cdn');
        $this->imgUrlHelper = CdnUrlGeneratorWithCloudfront::create(
            ['cdn' => $cdnConfig], 'cdn'
        );

        foreach($this->collection as $item) {
            $brand = $item->brand;
            $this->data['records'][] = [
                'id' => $brand->base_merchant_id,
                'name' => $brand->name,
                'slug' => Str::slug($brand->name),
                'image' => $this->transformImages($item),
            ];
        }

        return $this->data;
    }

    protected function transformImages($item, $imagePrefix = '')
    {
        $image = '';

        $brand = $item->brand;
        if (! $brand->mediaLogoOrig->isEmpty()) {
            $media = $brand->mediaLogoOrig->first();
            $localPath = $media->path;
            $cdnPath = $media->cdn_url;
            $image = $this->imgUrlHelper->getImageUrl($localPath, $cdnPath);
        }

        return $image;
    }
}
