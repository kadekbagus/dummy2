<?php

/**
 * Order Detail model.
 *
 * @author Budi <budi@gotomalls.com>
 */
class OrderDetail extends Eloquent
{
    protected $primaryKey = 'order_detail_id';

    protected $table = 'order_details';

    protected $guarded = [];

    public function brand_product_variant()
    {
        return $this->belongsTo('BrandProductVariant', 'brand_product_variant_id', 'brand_product_variant_id');
    }

    public function order_variant_details()
    {
        return $this->hasMany('OrderVariantDetail', 'order_detail_id', 'order_detail_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
