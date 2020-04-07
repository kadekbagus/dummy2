<?php

use Orbit\Controller\API\v1\Pub\BrandProduct\DataBuilder\SearchParamBuilder;
use Orbit\Controller\API\v1\Pub\BrandProduct\DataBuilder\SuggestionParamBuilder;
use Orbit\Helper\Searchable\Searchable;

/**
 * Brand Product Model with Searchable feature.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProduct extends Eloquent
{
    // Enable Searchable feature.
    use Searchable;

    protected $primaryKey = 'brand_product_id';

    protected $table = 'brand_products';

    protected $searchableCache = 'brand-product-list';

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
        if ($request->isSuggestion()) {
            return new SuggestionParamBuilder($request);
        }

        return new SearchParamBuilder($request);
    }

    public function brand()
    {
        return $this->belongsTo(
            BaseMerchant::class, 'brand_id', 'base_merchant_id'
        );
    }

    public function videos()
    {
        return $this->hasMany(BrandProductVideo::class);
    }

    public function categories()
    {
        return $this->belongsToMany(
            Category::class,
            'brand_product_categories',
            'brand_product_id',
            'category_id',
            null,
            'brand_product_category_id'
        );
    }

    public function brand_product_variants()
    {
        return $this->hasMany(BrandProductVariant::class);
    }

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'brand_product_id')
                    ->where('object_name', 'brand_product');
    }

    public function brand_product_main_photo()
    {
        return $this->media()->where('media_name_id', 'brand_product_main_photo');
    }

    public function brand_product_photos()
    {
    	return $this->media()->where('media_name_id', 'brand_product_photos');
    }
}