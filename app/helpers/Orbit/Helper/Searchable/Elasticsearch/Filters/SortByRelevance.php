<?php

namespace Orbit\Helper\Searchable\Elasticsearch\Filters;

trait SortByRelevance
{
    /**
     * Sort by relevance..
     *
     * @return [type] [description]
     */
    public function sortByRelevance()
    {
        $this->sort(['_score' => ['order' => 'desc']]);
    }
}
