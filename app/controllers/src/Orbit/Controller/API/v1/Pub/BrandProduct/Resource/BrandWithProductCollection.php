<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Resource;

use Config;
use Orbit\Helper\Resource\ResourceCollection;
use Orbit\Helper\Util\CdnUrlGeneratorWithCloudfront;
use Str;

/**
 * Brand with Product collection class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandWithProductCollection extends ResourceCollection
{
    private $imgUrlHelper = null;

    public function toArray()
    {
        $cdnConfig = Config::get('orbit.cdn');
        $this->imgUrlHelper = CdnUrlGeneratorWithCloudfront::create(
            ['cdn' => $cdnConfig], 'cdn'
        );

        foreach($this->collection as $item) {
            $this->data['records'][] = [
                'id' => $item->base_merchant_id,
                'name' => $item->name,
                'slug' => Str::slug($item->name),
                'image' => $this->transformImages($item),
            ];
        }

        return $this->data;
    }

    protected function transformImages($item, $imagePrefix = '')
    {
        $image = '';

        if (! $item->mediaLogoOrig->isEmpty()) {
            $media = $item->mediaLogoOrig->first();
            $localPath = $media->path;
            $cdnPath = $media->cdn_url;
            $image = $this->imgUrlHelper->getImageUrl($localPath, $cdnPath);
        }

        return $image;
    }
}
