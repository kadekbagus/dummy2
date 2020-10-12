<?php

namespace Orbit\Helper\Searchable;

interface SearchProviderInterface
{
    public function count($query);

    public function search($query);
}
