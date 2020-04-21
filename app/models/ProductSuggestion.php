<?php

use Orbit\Controller\API\v1\Pub\Product\DataBuilder\SuggestionParamBuilder;
use Orbit\Helper\Searchable\Searchable;

/**
 * Product Affiliation Suggestion Model.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ProductSuggestion
{
    use Searchable;

    protected $searchableCache = 'product-affiliation-suggestion-list';

    /**
     * Return search query builder.
     *
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    protected function getSearchQueryBuilder($request)
    {
        return new SuggestionParamBuilder($request);
    }
}
