<?php

namespace Orbit\Helper\Resource;

/**
 * Trait that enables the host to transform list of images
 * to simpler format:
 *
 * $images['variant'] = 'image_url';
 *
 * @author Budi <budi@gotomalls.com>
 */
trait ImageTransformer {

    /**
     * The image prefix that will be used to transform image/media.
     * @var null
     */
    protected $imagePrefix = null;

    /**
     * Transform a collection of media into key-value array of variant-url.
     *
     * @param  Illuminate\Database\Eloquent\Model $item the model instance
     * @param  string $imagePrefix the custom image prefix
     *
     * @return array list of images
     */
    protected function transformImages($item, $imagePrefix = '')
    {
        $images = null;

        if ($item->media->count() > 0)
        {
            $images = [];
            $imagePrefix = ! empty($imagePrefix)
                ? $imagePrefix
                : $this->imagePrefix;

            foreach($item->media as $media) {
                $variant = str_replace(
                    $imagePrefix,
                    '',
                    $media->media_name_long
                );

                $images[$variant] = $media->image_url;
            }
        }

        return $images;
    }

    /**
     * Transform a collection of media into array of media for older api.
     *
     * @param  Illuminate\Database\Eloquent\Model $item the model/resource
     *
     * @return array
     */
    protected function transformImagesOld($item)
    {
        $images = [];
        foreach($item->media as $media) {
            $images[] = [
                'media_id' => $media->media_id,
                'path' => $media->path,
                'media_name_long' => $media->media_name_long,
                'object_id' => $media->object_id,
            ];
        }

        return $images;
    }
}
