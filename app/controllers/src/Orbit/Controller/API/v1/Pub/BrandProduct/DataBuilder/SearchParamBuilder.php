<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\DataBuilder;

use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\CategoryFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\CitiesFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\CountryFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\KeywordFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\MallFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\StatusFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\StoreFilter;
use Orbit\Controller\API\v1\Pub\Product\SearchableFilters\MarketplaceFilter;
use Orbit\Controller\API\v1\Pub\Product\SearchableFilters\PriceRangeFilter;
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
        StoreFilter,
        PriceRangeFilter;

    protected $objectType = 'products';

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
        'cities',
        'store_id',
        'mall_id',
        'brand_id',
        'min_price',
        'max_price',
        'marketplaces',
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
     * Sort by name.
     *
     * @override
     */
    public function sortByName($language = 'id', $sortMode = 'asc')
    {
        parent::sortByName($language, $sortMode);

        // After sorting by name, then sort by relevance.
        $this->sortByRelevance();
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

        $this->request->has('brand_id', function($brandId) {
            $this->filterByBrand($brandId);
        });

        $this->request->has('min_price', function($minPrice) {
            $this->filterByMinPrice($minPrice);
        });

        $this->request->has('max_price', function($maxPrice) {
            $this->filterByMaxPrice($maxPrice);
        });

        $this->setBodyParams([
            '_source' => [
                'exclude' => ['description'],
            ],
        ]);
    }
}
