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

    protected function addCustomParam()
    {
        parent::addCustomParam();

        $this->request->has('available_store_keyword', function($keyword) {
            $this->filterAvailableStoreByKeyword($keyword);
        });
    }
}
