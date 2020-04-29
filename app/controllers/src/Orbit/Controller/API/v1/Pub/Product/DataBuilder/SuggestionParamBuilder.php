<?php

namespace Orbit\Controller\API\v1\Pub\Product\DataBuilder;

use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\CategoryFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\CitiesFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\CountryFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\KeywordFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\StatusFilter;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\StoreFilter;
use Orbit\Controller\API\v1\Pub\Product\DataBuilder\SearchParamBuilder;
use Orbit\Helper\Searchable\Elasticsearch\ESSearchParamBuilder;

/**
 * Product affiliation suggestion query builder.
 *
 * @author Budi <budi@gotomalls.com>
 */
class SuggestionParamBuilder extends SearchParamBuilder
{
    public function filterByCategories($categories, $logic = 'must')
    {
        parent::filterByCategories($categories, 'should');
    }

    public function filterByBrand($brandId, $logic = 'must')
    {
        parent::filterByBrand($brandId, 'should');
    }

    public function filterByKeyword($keyword = '', $logic = 'must')
    {
        parent::filterByKeyword($keyword, 'should');
    }

    protected function addCustomParam()
    {
        parent::addCustomParam();

        $this->request->has('except_id', function($exceptId) {
            $this->exclude($exceptId);
        });
    }
}
