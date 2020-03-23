<?php

namespace Orbit\Helper\Searchable\Elasticsearch\Filters;

trait SortByUpdatedDate
{
    /**
     * Sort store by updated date.
     *
     * @param  string $sortingScript [description]
     * @return [type]                [description]
     */
    public function sortByUpdatedDate($order = 'desc')
    {
        $this->sort([
            'updated_at' => [
                'order' => $order
            ]
        ]);
    }
}
