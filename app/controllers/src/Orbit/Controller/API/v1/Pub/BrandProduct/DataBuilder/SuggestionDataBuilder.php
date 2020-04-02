<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\DataBuilder;

use Orbit\Controller\API\v1\Pub\BrandProduct\DataBuilder\SearchDataBuilder;

/**
 * Brand product suggestion query builder.
 *
 * @author Budi <budi@gotomalls.com>
 */
class SuggestionDataBuilder extends SearchDataBuilder
{
    protected function getSortBy()
    {
        return 'relevance';
    }

    protected function addCustomParam()
    {
        parent::addCustomParam();

        $this->request->has('brand_id', function($brandId) {
            $this->filterByBrand($brandId);
        });

        $this->exclude($this->request->except_id);
    }
}
