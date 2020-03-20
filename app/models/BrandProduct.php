<?php

use Orbit\Controller\API\v1\Pub\BrandProduct\DataBuilder\SearchDataBuilder;
use Orbit\Helper\Searchable\Searchable;

/**
 * Brand Product Model
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProduct extends Eloquent
{
    // Provide Searchable feature.
    // To disable Searchable feature and its initialization, just comment
    // following line.
    use Searchable;

    protected $primaryKey = 'brand_product_id';

    /**
     * Get builder instance.
     * Must be implemented by Model with searchable feature.
     *
     * @return builder instance.
     */
    public function getSearchQueryBuilder($request)
    {
        return new SearchDataBuilder($request);
    }
}
