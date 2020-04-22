<?php

namespace Orbit\Controller\API\v1\Pub\Product\DataBuilder;

use BaseStore;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\CategoryFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\CitiesFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\CountryFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\KeywordFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\StatusFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\StoreFilter;
use Orbit\Helper\Searchable\Elasticsearch\ESSearchParamBuilder;

/**
 * Brand product search query builder.
 *
 * @author Budi <budi@gotomalls.com>
 */
class SearchParamBuilder extends ESSearchParamBuilder
{
    // Compose search filters as needed.
    use StatusFilter,
        KeywordFilter,
        CategoryFilter,
        CountryFilter,
        CitiesFilter,
        StoreFilter;

    protected $objectType = 'product_affiliations';

    /**
     * List of cached request params.
     * @var array
     */
    protected $cachedRequestParams = [
        'skip',
        'take',
        'sortby',
        'sortmode',
        'keyword',
        'category_id',
        'country',
        'brand_id',
    ];

    /**
     * Override sort by created date (default field is 'begin_date').
     *
     * @param  string $sortingScript [description]
     * @return [type]                [description]
     */
    public function sortByCreatedDate($order = 'desc')
    {
        $this->sort([
            'created_at' => [
                'order' => $order
            ]
        ]);
    }

    /**
     * Set keyword priority/weight against each field.
     *
     * @override
     * @param [type] $objType [description]
     * @param [type] $keyword [description]
     */
    protected function setPriorityForQueryStr($objType, $keyword, $logic = 'must')
    {
        $priorityProductName = isset($this->esConfig['priority'][$objType]['product_name']) ?
            $this->esConfig['priority'][$objType]['product_name'] : '^10';

        $priorityMarketplaceName = isset($this->esConfig['priority'][$objType]['marketplace_name']) ?
            $this->esConfig['priority'][$objType]['marketplace_name'] : '^8';

        $priorityBrandName = isset($this->esConfig['priority'][$objType]['brand_name']) ?
            $this->esConfig['priority'][$objType]['brand_name'] : '^6';

        $priorityDescription = isset($this->esConfig['priority'][$objType]['description']) ?
            $this->esConfig['priority'][$objType]['description'] : '^4';

        $this->{$logic}([
            'bool' => [
                'should' => [
                    [
                        'query_string' => [
                            'query' => '*' . $keyword . '*',
                            'fields' => [
                                'product_name' . $priorityProductName,
                                'marketplaces.marketplace_name' . $priorityMarketplaceName,
                                'brand_name' . $priorityBrandName,
                                'description' . $priorityDescription,
                            ]
                        ]
                    ],
                ]
            ]
        ]);
    }

    /**
     * Add object-specific filter/sort params.
     *
     * @return array
     */
    protected function addCustomParam()
    {
        // Filter by status
        $this->filterByStatus('active');

        // Filter by categories
        $this->request->has('category_id', function($categories) {
            $this->filterByCategories($categories);
        });

        $this->request->has('brand_id', function($brandId) {
            $this->filterByBrand($brandId);
        });

        $this->request->has('store_id', function($storeId) {
            // Reset search query, only filter active products which linked
            // into specific store/brand.
            $this->setBodyParams(['query' => []]);
            $this->filterByStatus('active');

            // Get the brand from store_id
            $baseStore = BaseStore::select('base_merchant_id')
                ->where('base_store_id', $storeId)->first();

            $brandId = $storeId;
            if (! empty($baseStore)) {
                $brandId = $baseStore->base_merchant_id;
            }

            $this->filterByBrand($brandId);
        });

        // Exclude description from the result,
        // because we don't need it on listing.
        $this->setBodyParams([
            '_source' => [
                'exclude' => ['description'],
            ],
        ]);
    }
}
