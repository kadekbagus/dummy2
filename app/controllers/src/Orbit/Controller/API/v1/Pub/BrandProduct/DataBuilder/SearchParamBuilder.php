<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\DataBuilder;

use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\CategoryFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\CitiesFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\CountryFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\KeywordFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\MallFilter;
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
        MallFilter,
        StoreFilter;

    protected $objectType = 'products';

    /**
     * List of cached request params.
     * @var array
     */
    protected $cachedRequestParams = [
        'sortby',
        'sortmode',
        'keyword',
        'category_id',
        'country',
        'cities',
        'store_id',
        'mall_id',
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

        // Filter by mall
        $this->request->has('mall_id', function($mallId) {
            $this->filterByMall($mallId);
        });

        // Filter by store
        $this->request->has('store_id', function($storeId) {
            $this->filterByStore($storeId);
        });
    }
}
