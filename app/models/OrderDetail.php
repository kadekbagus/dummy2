<?php

/**
 * Order Detail model.
 *
 * @author Budi <budi@gotomalls.com>
 */
class OrderDetail extends Eloquent
{
    protected $guarded = [];

    protected $primaryKey = 'order_detail_id';

    public function brand_product_variant()
    {
        return $this->belongsTo('BrandProductVariant');
    }

    public function variant_details()
    {
        return $this->hasMany('OrderVariantDetail');
    }
}
