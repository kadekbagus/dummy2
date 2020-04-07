<?php

use Orbit\Controller\API\v1\Pub\BrandProduct\DataBuilder\AvailableStoreSearchParamBuilder;
use Orbit\Helper\Searchable\Searchable;

/**
 * Brand Product Available Store Model with Searchable feature.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductAvailableStore
{
    // Enable Searchable feature.
    use Searchable;

    protected $searchableCache = 'brand-product-available-store-list';

    /**
     * Get search query builder instance, which helps building
     * final search query based on $request param.
     *
     * @see Orbit\Helper\Searchable\Searchable
     *
     * @return null|DataBuilder $builder builder instance or null if we don't
     *                                   need one.
     */
    public function getSearchQueryBuilder($request)
    {
        return new AvailableStoreSearchParamBuilder($request);
    }
}