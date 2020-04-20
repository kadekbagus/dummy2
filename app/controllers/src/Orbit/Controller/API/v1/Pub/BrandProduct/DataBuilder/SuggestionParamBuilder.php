<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\DataBuilder;

use Orbit\Controller\API\v1\Pub\BrandProduct\DataBuilder\SearchParamBuilder;

/**
 * Brand product suggestion query builder.
 *
 * @author Budi <budi@gotomalls.com>
 */
class SuggestionParamBuilder extends SearchParamBuilder
{
    /**
     * Force the sorting method to 'relevance'.
     *
     * @return string
     */
    protected function getSortingParams()
    {
        return ['relevance' => 'desc'];
    }

    public function filterByCategories($categories, $logic = 'must')
    {
        parent::filterByCategories($categories, 'should');
    }

    public function filterByStore($storeId, $logic = 'must')
    {
        parent::filterByStore($storeId, 'should');
    }

    public function filterByBrand($brandId, $logic = 'must')
    {
        parent::filterByBrand($brandId, 'should');
    }

    public function filterByKeyword($keyword, $logic = 'must')
    {
        parent::filterByKeyword($keyword, 'should');
    }

    protected function addCustomParam()
    {
        parent::addCustomParam();

        $this->exclude($this->request->except_id);
    }
}
