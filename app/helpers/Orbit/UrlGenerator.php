<?php
namespace Orbit;

use Config;
/**
 * Extends url generator to use separate asset domain.
 *
 * @package Orbit
 */
class UrlGenerator extends \Illuminate\Routing\UrlGenerator
{
    /**
     * Determine asset URL based on "orbit.assets.root" config, or defers to parent if not set.
     *
     * @param string $path path
     * @param bool|null $secure secure or not (or use existing protocol)
     * @return string
     */
    public function asset($path, $secure = null)
    {
        if ($this->isValidUrl($path)) return $path;

        $assetRoot = Config::get('orbit.assets.root', false);
        if (!$assetRoot) {
            // not defined, use parent
            return parent::asset($path, $secure);
        }

        $scheme = $this->getScheme($secure);
        $start = starts_with($assetRoot, 'http://') ? 'http://' : 'https://';
        $root = preg_replace('~'.$start.'~', $scheme, $assetRoot, 1);

        return $this->removeIndex($root).'/'.trim($path, '/');
    }

}
