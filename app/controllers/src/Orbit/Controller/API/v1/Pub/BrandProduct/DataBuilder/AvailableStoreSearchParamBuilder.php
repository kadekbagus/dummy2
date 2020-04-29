<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\DataBuilder;

use Orbit\Controller\API\v1\Pub\BrandProduct\DataBuilder\SearchParamBuilder;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\AvailableStoreFilter;

/**
 * Available store query builder.
 *
 * @author Budi <budi@gotomalls.com>
 */
class AvailableStoreSearchParamBuilder extends SearchParamBuilder
{
    // Add ability to filter available store by keyword.
    use AvailableStoreFilter;

    /**
     * Need to find a way to make `take` value unlimited.
     * At the moment, force it to maximum allowed by ES.
     *
     * @return [type] [description]
     */
    protected function getTakeValue()
    {
        return 10000 - (int) $this->getSkipValue();
    }

    /**
     * Force sorting by relevance (score).
     *
     * @return array
     */
    protected function getSortingParams()
    {
        return ['relevance' => 'desc'];
    }

    /**
     * Override default filter by store. Filter by store should not available.
     *
     * @param  [type] $storeId [description]
     * @return [type]          [description]
     */
    public function filterByStore($storeId, $logic = 'must')
    {
        return;
    }

    /**
     * Override default filter by brand.
     * Add filter brand_id inside link_to_stores, so only stores
     * for that brand returned.
     *
     * @param  string $brandId [description]
     * @return [type]          [description]
     */
    public function filterByBrand($brandId = '', $logic = 'must')
    {
        parent::filterByBrand($brandId, $logic);

        if (is_string($brandId)) {
            $brandId = [$brandId];
        }

        $brandId = array_map(function($brandId) {
            return [
                'match' => [
                    'link_to_stores.brand_id' => $brandId,
                ],
            ];
        }, $brandId);

        $this->{$logic}([
            'nested' => [
                'path' => 'link_to_stores',
                'query' => [
                    'bool' => [
                        'should' => $brandId
                    ]
                ],
            ]
        ]);
    }

    protected function addCustomParam()
    {
        parent::addCustomParam();

        $this->request->has('available_store_keyword', function($keyword) {
            $this->filterAvailableStoreByKeyword($keyword);
        });

        // Only return inner hits of the nested path link_to_stores,
        // because we don't need the other data (e.g _source).
        $this->setParams(['fields' => ['inner_hits']]);
    }
}
