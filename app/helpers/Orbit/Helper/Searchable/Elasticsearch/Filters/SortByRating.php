<?php

namespace Orbit\Helper\Searchable\Elasticsearch\Filters;

trait SortByRating
{
    /**
     * Sort store by rating.
     *
     * @param  string $sortingScript [description]
     * @return [type]                [description]
     */
    public function sortByRating($sortingScript = '', $sortMode = 'desc')
    {
        $this->sort([
            '_script' => [
                'script' => $sortingScript,
                'type' => 'number',
                'order' => $sortMode
            ]
        ]);
    }
}
