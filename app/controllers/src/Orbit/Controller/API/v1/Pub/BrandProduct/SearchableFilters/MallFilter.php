<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters;

/**
 * Implement filter by mall specific for brand product.
 */
trait MallFilter
{
    public function filterByMall($mallId)
    {
        // Ignore mall filter if set to count result only.
        if ($this->countOnly) {
            return;
        }

        $this->must([
            'nested' => [
                'path' => 'link_to_malls',
                'query' => [
                    'match' => [
                        'link_to_malls.mall_id' => $mallId
                    ]
                ],
            ]
        ]);
    }
}
