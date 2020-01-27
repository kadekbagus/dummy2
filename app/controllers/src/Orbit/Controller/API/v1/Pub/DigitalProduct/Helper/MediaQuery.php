<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Helper;

use Config;
use DB;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Trait that make the host able to resolve image
 * variant requested by api client.
 *
 * @author Budi <budi@gotomalls.com>
 */
trait MediaQuery {

    protected $imageQuery;

    /**
     * Resolve image variant that will be returned.
     *
     * @param  string $default [description]
     * @return [type]          [description]
     */
    protected function resolveImageVariants($prefix = '', $default = '')
    {
        $imageVariants = explode(',', OrbitInput::get('image_variant', $default));

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

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
