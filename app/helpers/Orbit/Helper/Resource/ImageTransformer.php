<?php namespace Orbit\Helper\Resource;

/**
 * Trait that enables the host to transform list of images
 * to simpler format images->variant = image_url
 *
 * @author Budi <budi@gotomalls.com>
 */
trait ImageTransformer {

    private $imagePrefix = '';

    /**
     * Set image prefix of media name.
     * Will be used to make shorter version of variant name by replacing the prefix, e.g.
     * 'game_image_desktop_thumb' --> 'desktop_thumb'
     *
     * @param string $imagePrefix [description]
     */
    protected function setImagePrefix($imagePrefix = '')
    {
        $this->imagePrefix = $imagePrefix;
    }

    /**
     * Transform a collection of media into key-value array of variant-url.
     *
     * @param  [type] $item [description]
     * @return [type]       [description]
     */
    protected function transformImages($item)
    {
        $images = null;

        foreach($item->media as $media) {
            $variant = str_replace($this->imagePrefix, '', $media->media_name_long);
            $images[$variant] = $media->image_url;
        }

        return $images;
    }
}
