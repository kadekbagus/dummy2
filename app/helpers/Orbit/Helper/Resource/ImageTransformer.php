<?php namespace Orbit\Helper\Resource;

/**
 * Trait that enables the host to transform list of images
 * to simpler format images->variant = image_url
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
     * @param  Illuminate\Database\Eloquent\Model the model instance
     * @return array list of images
     */
    protected function transformImages($item, $imagePrefix = '')
    {
        $images = null;

        $imagePrefix = ! empty($imagePrefix) ? $imagePrefix : $this->imagePrefix;

        foreach($item->media as $media) {
            $variant = str_replace($imagePrefix, '', $media->media_name_long);
            $images[$variant] = $media->image_url;
        }

        return $images;
    }
}
