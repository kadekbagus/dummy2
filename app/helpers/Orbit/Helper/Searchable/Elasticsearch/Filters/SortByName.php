<?php

namespace Orbit\Helper\Searchable\Elasticsearch\Filters;

trait SortByName
{
    /**
     * Sort by name..
     *
     * @return [type] [description]
     */
    public function sortByName($language = 'id', $sortMode = 'asc')
    {
        $this->sort(['lowercase_name' => ['order' => $sortMode]]);
    }
}
