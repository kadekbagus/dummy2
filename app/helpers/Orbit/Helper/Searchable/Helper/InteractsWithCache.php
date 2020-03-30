<?php

namespace Orbit\Helper\Searchable\Helper;

use Cache;
use Orbit\Helper\Util\SimpleCache;

trait InteractsWithCache
{
    protected $cacheKey = [];

    public function getCacheKey()
    {
        return SimpleCache::transformDataToHash($this->cacheKey);
    }

    public function put($key, $data)
    {
        Cache::put($key, $data);
    }

    public function get($key)
    {
        Cache::get($key);
    }
}
