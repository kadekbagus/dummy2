<?php

namespace Orbit\Controller\API\v1\Pub\Product\SearchableFilters;

/**
 * Implement price range filter.
 */
trait PriceRangeFilter
{
    /**
     * Filter by price range.
     *
     * @param  string $priceRange price range, separated by dash '-'.
     * @param string $logic logic that should wrap this query.
     * @return void
     */
    public function filterByPriceRange($priceRange = '', $logic = 'filter')
    {
        $priceRange = explode('-', $priceRange);
        $priceRangeQuery = [];

        if (isset($priceRange[0]) && ! empty($priceRange[0])) {
            $priceRangeQuery['lowest_selling_price'] = [
                'gte' => (double) $priceRange[0]
            ];
        }

        if (isset($priceRange[1]) && ! empty($priceRange[1])) {
            $priceRangeQuery['highest_selling_price'] = [
                'lte' => (double) $priceRange[1],
            ];
        }

        if (! empty($priceRangeQuery)) {
            $this->{$logic}([
                'range' => $priceRangeQuery
            ]);
        }
    }
}
