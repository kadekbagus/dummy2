<?php

/**
 * Brand Product Variant
 */
class BrandProductVariant extends Eloquent
{
    protected $table = 'brand_product_variants';

    protected $primaryKey = 'brand_product_variant_id';

    public function options()
    {
        return $this->hasMany(VariantOption::class);
    }
}
