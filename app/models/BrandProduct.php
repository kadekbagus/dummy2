<?php

use Orbit\Controller\API\v1\Pub\BrandProduct\DataBuilder\SearchDataBuilder;
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

    /**
     * Get search query builder instance, which helps building
     * final search query based on $request param.
     *
     * @see Searchable\Searchable@search
     *
     * @return null|DataBuilder $builder builder instance or null if we don't
     *                                   need one.
     */
    public function getSearchQueryBuilder($request)
    {
        return new SearchDataBuilder($request);
    }

    public function videos()
    {
        return $this->hasMany(BrandProductVideo::class);
    }

    public function categories()
    {
        return $this->belongsToMany(
            Category::class,
            'brand_product_categories'
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

    public function BrandProductMainPhotos()
    {
        return $this->media()->where('media_name_id', 'brand_product_main_photo');
    }

    public function BrandProductPhotos()
    {
    	return $this->media()->where('media_name_id', 'brand_product_photos');
    }
}