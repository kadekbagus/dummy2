<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters;

/**
 * Implement filter by stores specific for brand product.
 */
trait StoreFilter
{
    public function filterByStore($storeId, $logic = 'must')
    {
        if (is_string($storeId)) {
            $storeId = [$storeId];
        }

        $storeId = array_map(function($storeId) {
            return [
                'match' => [
                    'link_to_stores.store_id' => $storeId
                ],
            ];
        }, $storeId);

        $this->{$logic}([
            'nested' => [
                'path' => 'link_to_stores',
                'query' => [
                    'bool' => [
                        'should' => $storeId
                    ]
                ],
            ]
        ]);
    }

    public function filterByBrand($brandId, $logic = 'must')
    {
        if (is_string($brandId)) {
            $brandId = [$brandId];
        }

        $brandId = array_map(function($brandId) {
            return [
                'match' => [
                    'brand_id' => $brandId,
                ],
            ];
        }, $brandId);

        $this->{$logic}([
            'query' => [
                'bool' => [
                    'should' => $brandId
                ],
            ]
        ]);
    }
}
