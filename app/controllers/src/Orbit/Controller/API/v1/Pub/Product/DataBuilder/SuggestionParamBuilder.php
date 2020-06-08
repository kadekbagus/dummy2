<?php

namespace Orbit\Controller\API\v1\Pub\Product\DataBuilder;

use Orbit\Controller\API\v1\Pub\Product\DataBuilder\SearchParamBuilder;

/**
 * Product affiliation suggestion query builder.
 *
 * @author Budi <budi@gotomalls.com>
 */
class SuggestionParamBuilder extends SearchParamBuilder
{
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
