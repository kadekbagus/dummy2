<?php

namespace Orbit\Controller\API\v1\Pub\Product\SearchableFilters;

/**
 * Implement price range filter.
 *
 * @author Budi <budi@dominopos.com>
 */
trait PriceRangeFilter
{
    /**
     * Filter by min price
     *
     * @param  int $minPrice minimum price
     * @param string $logic logic that should wrap this filter.
     * @return void
     */
    public function filterByMinPrice($minPrice = 0, $logic = 'filter')
    {
        if (! empty($minPrice)) {
            $this->{$logic}([
                'range' => [
                    'lowest_selling_price' => [
                        'gte' => (double) $minPrice,
                    ],
                ],
            ]);
        }
    }

    /**
     * Filter by max price
     *
     * @param  int $maxPrice maximum price
     * @param string $logic logic that should wrap this filter.
     * @return void
     */
    public function filterByMaxPrice($maxPrice = 0, $logic = 'filter')
    {
        if (! empty($maxPrice)) {
            $this->{$logic}([
                'range' => [
                    'highest_selling_price' => [
                        'lte' => (double) $maxPrice,
                    ],
                ],
            ]);
        }
    }
}
