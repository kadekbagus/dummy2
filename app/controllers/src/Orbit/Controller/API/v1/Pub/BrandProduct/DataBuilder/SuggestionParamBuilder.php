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
    protected $cacheContext = 'product-suggestion-list';

    /**
     * Force the sorting method to 'relevance'.
     *
     * @return string
     */
    protected function getSortingParams()
    {
        return ['relevance' => 'desc'];
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
