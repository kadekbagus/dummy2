<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters;

/**
 * Implement filter by status.
 */
trait StatusFilter
{
    /**
     * Filter by categories.
     *
     * @param  [type] $cities [description]
     * @return [type]         [description]
     */
    public function filterByStatus($status = 'active')
    {
        $this->filter([
            'match' => [
                'status' => $status,
            ],
        ]);
    }
}
