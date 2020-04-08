<?php

namespace Orbit\Helper\Searchable\Helper;

use Cache;
use Orbit\Helper\Util\SimpleCache;

/**
 * A helper that provide host classes ability to generate a *standard*
 * cache key based on their needs.
 *
 * @todo  Should a standalone trait (not under searchable)
 *
 * @author Budi <budi@gotomalls.com>
 */
trait CacheableKeys
{
    protected $cacheKeys = [];

    public function getCacheKey()
    {
        return SimpleCache::transformDataToHash($this->cacheKeys);
    }

    protected function buildCacheKey()
    {
        return $this->cacheKeys;
    }
}
