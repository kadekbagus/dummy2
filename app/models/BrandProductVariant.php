<?php

/**
 * Brand Product Variant
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductVariant extends Eloquent
{
    protected $table = 'brand_product_variants';

    protected $primaryKey = 'brand_product_variant_id';

    public function variants()
    {
        return $this->hasMany(Variant::class);
    }
}
