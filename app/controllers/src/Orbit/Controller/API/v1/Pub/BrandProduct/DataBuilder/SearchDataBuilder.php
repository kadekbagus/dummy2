<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\DataBuilder;

use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\CategoryFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\KeywordFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\MallFilter;
use Orbit\Helper\Searchable\Elasticsearch\ESSearchDataBuilder;
use Orbit\Helper\Searchable\Elasticsearch\Filters\CitiesFilter;
use Orbit\Helper\Searchable\Elasticsearch\Filters\CountryFilter;

/**
 * Brand product search query builder.
 *
 * @author Budi <budi@gotomalls.com>
 */
class SearchDataBuilder extends ESSearchDataBuilder
{
    use KeywordFilter,
        CategoryFilter,
        CountryFilter,
        CitiesFilter,
        MallFilter;

    protected $objectType = 'products';

    /**
     * Override sort by created date.
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
     * Sort by Nearest..
     *
     * @return [type] [description]
     */
    public function sortByNearest($ul = null)
    {
        $this->nearestSort('link_to_stores', 'link_to_stores.position', $ul);
    }

    /**
     * Build search param.
     *
     * @return array
     */
    public function build()
    {
        parent::build();

        // Filter by country
        $this->request->has('country', function($country) {
            $this->filterByCountry($country);
        });

        // Filter by cities
        $this->request->has('cities', function($cities) {
            $this->filterByCities($cities);
        });

        $this->request->has('category_id', function($categories) {
            $this->filterByCategories($categories);
        });

        // Filter by keyword...
        $this->request->has('keyword', function($keyword) {
            $this->filterByKeyword($keyword);
        });

        $this->request->has('mall_id', function($mallId) {
            $this->filterByMall($mallId);
        });

        // Sort...
        $sortBy = $this->request->sortby ?: 'created_date';
        $sortMode = $this->request->sortmode ?: 'desc';

        switch ($sortBy) {
            case 'name':
                $this->sortByName($sortMode);
                break;

            case 'nearest':
                $this->sortByNearest($this->request->ul);
                break;

            // case 'rating':
            //     $this->sortByRating($ratingScript);
            //     break;

            case 'created_date':
            default:
                // Default sort by latest.
                $this->sortByCreatedDate();
                break;
        }

        return $this->buildSearchParam();
    }
}
