<?php

namespace Orbit\Controller\API\v1\Pub\Product\SearchableFilters;

/**
 * Implement filter by marketplace.
 */
trait MarketplaceFilter
{
    /**
     * Filter by marketplace.
     *
     * @param  string $priceRange price range, separated by dash '-'.
     * @param string $logic logic that should wrap this query.
     * @return void
     */
    public function filterByMarketplace($marketplaceNames = [], $logic = 'filter')
    {
        $marketplaceNames = array_map(function($marketplaceName) {
            return [
                'match' => [
                    'marketplace_names.marketplace_name' => $marketplaceName,
                ],
            ];
        }, $marketplaceNames);

        if (empty($marketplaceNames)) {
            return;
        }

        $this->{$logic}([
            'nested' => [
                'path' => 'marketplace_names',
                'query' => [
                    'bool' => [
                        'should' => $marketplaceNames,
                        'minimumShouldMatch' => 1,
                    ]
                ],
            ]
        ]);
    }
}
