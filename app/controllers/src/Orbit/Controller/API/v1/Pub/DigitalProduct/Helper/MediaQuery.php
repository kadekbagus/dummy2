<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Helper;

use Config;
use DB;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Trait that make the host able to resolve image
 * variant requested by api client.
 *
 * This trait requires a presence of property 'imagePrefix' on the
 * host class.
 *
 * @author Budi <budi@gotomalls.com>
 */
trait MediaQuery {

    protected $imageQuery;

    /**
     * Resolve image variant that will be used to query from table media.
     *
     * First, we would try reading from 'image_variant'.
     * If not empty, then use it. Done.
     *
     * If empty, then try reading from 'device'.
     * If device is mobile, then load all mobile variants
     * If desktop, then load all desktop variants + original version.
     *
     * If none of those params were set, then return all variant.
     *
     * @todo  should we read from 'device' first, instead of image_variant?
     * @param  string $default [description]
     * @return [type]          [description]
     */
    protected function resolveImageVariants($prefix = '', $default = '')
    {
        // Try getting image variant from 'image_variant'
        $imageVariants = explode(',', OrbitInput::get('image_variant', $default));
        $imageVariants = array_filter($imageVariants);

        if (isset($this->imagePrefix) && ! empty($this->imagePrefix)) {
            $prefix = $this->imagePrefix;
        }

        // If 'image_variant' is empty, then try getting from 'device'.
        if (empty($imageVariants)) {

            $device = OrbitInput::get('device');

            if ($device === 'mobile') {
                $imageVariants = ['mobile_medium', 'mobile_thumb'];
            }
            else if ($device === 'desktop') {
                $imageVariants = ['desktop_medium', 'desktop_thumb', 'orig'];
            }
        }

        // If image_variant and device are empty,
        // then return all variant as fallback.

        return array_map(function($item) use ($prefix) {
                return $prefix . trim($item);
            }, $imageVariants);
    }

    /**
     * Setup image url query.
     * @return [type] [description]
     */
    protected function setupImageUrlQuery()
    {
        $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
        $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';
        $tablePrefix = DB::getTablePrefix();

        $this->imageQuery = "CONCAT({$this->quote($urlPrefix)}, {$tablePrefix}media.path) as image_url";
        if ($usingCdn) {
            $this->imageQuery = "CASE WHEN {$tablePrefix}media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, {$tablePrefix}media.path) ELSE {$tablePrefix}media.cdn_url END as image_url";
        }
    }

    /**
     * Build a basic media relation query. Can be overridden when needed.
     *
     * @param array a relationship query to media.
     */
    protected function buildMediaQuery()
    {
        return [
            'media' => function($query) {
                $query->select(
                    'object_id',
                    'media_name_long',
                    DB::raw($this->imageQuery)
                );

                $imageVariants = $this->resolveImageVariants();

                if (! empty($imageVariants)) {
                    $query->whereIn('media_name_long', $imageVariants);
                }
            }
        ];
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
