<?php

namespace Orbit\Helper\Searchable\Helper;

interface Cacheable
{
    public function getCacheKey();

    public function put($key, $data);

    public function get($key);
}
