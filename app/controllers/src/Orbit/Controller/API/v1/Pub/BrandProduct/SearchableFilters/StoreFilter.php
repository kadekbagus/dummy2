<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters;

/**
 * Implement filter by stores specific for brand product.
 */
trait StoreFilter
{
    public function filterByStore($storeId)
    {
        $this->must([
            'nested' => [
                'path' => 'link_to_stores',
                'query' => [
                    'match' => [
                        'link_to_stores.store_id' => $storeId
                    ]
                ],
            ]
        ]);
    }

    public function filterByBrand($brandId)
    {
        $this->should([
            'match' => [
                'brand_id' => $brandId
            ]
        ]);
    }
}
