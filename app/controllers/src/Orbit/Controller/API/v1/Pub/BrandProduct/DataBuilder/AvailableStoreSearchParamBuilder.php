<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\DataBuilder;

use Orbit\Controller\API\v1\Pub\BrandProduct\DataBuilder\SearchParamBuilder;
use Orbit\Controller\API\v1\Pub\BrandProduct\SearchableFilters\AvailableStoreFilter;

/**
 * Brand product search query builder.
 *
 * @author Budi <budi@gotomalls.com>
 */
class AvailableStoreSearchParamBuilder extends SearchParamBuilder
{
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
