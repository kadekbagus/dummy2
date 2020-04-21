<?php

use Orbit\Helper\Searchable\Searchable;

class Product extends Eloquent
{
    use Searchable;

    protected $searchableCache = 'product-affiliation-list';

    protected function getSearchQueryBuilder($request)
    {
        return new SearchParamBuilder($request);
    }

    /**
    * Product Model
    *
    * @author kadek <kadek@dominopos.com>
    *
    */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'products';

    protected $primaryKey = 'product_id';

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'product_id')
                    ->where('object_name', 'product');
    }

    public function merchants()
    {
        return $this->belongsToMany('BaseMerchant', 'product_link_to_object', 'product_id', 'object_id')
            ->select('base_merchant_id', 'name')
            ->where('product_link_to_object.object_type', '=', 'brand');
    }

    public function categories()
    {
        return $this->belongsToMany('Category', 'product_link_to_object', 'product_id', 'object_id')
            ->select('category_id', 'category_name')
            ->where('product_link_to_object.object_type', '=', 'category');
    }

    public function marketplaces()
    {
        return $this->belongsToMany('Marketplace', 'product_link_to_object', 'product_id', 'object_id')
            ->select(
                'marketplace_id',
                'name',
                'product_link_to_object.product_url',
                'product_link_to_object.original_price',
                'product_link_to_object.selling_price'
            )
            ->where('product_link_to_object.object_type', '=', 'marketplace');
    }

    public function country()
    {
        return $this->belongsTo('Country', 'country_id', 'country_id')
            ->select('country_id', 'name');
    }

    public function videos()
    {
        return $this->hasMany('ProductVideo');
    }
}
