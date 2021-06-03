<?php

/**
 * Cart Item model.
 *
 * @author Budi <budi@gotomalls.com>
 */
class CartItem extends Eloquent
{
    public function brand_product_variant()
    {
        return $this->belongsTo(BrandProductVariant::class);
    }
}
