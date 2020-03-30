<?php

namespace Orbit\Helper\Searchable\Elasticsearch\Filters;

trait ExcludeFilter
{
    /**
     * Add items into excludedIds list.
     * The list will be added into the search param body query later.
     *
     * @param  array  $excludedId [description]
     * @return void
     */
    public function exclude($excludedIds = null)
    {
        if ( empty($excludedIds)) return;

        if (! is_array($excludedIds)) {
            $excludedIds = [$excludedIds];
        }

        $this->excludedIds = array_unique(
            array_merge($this->excludedIds, $excludedIds)
        );
    }
}
