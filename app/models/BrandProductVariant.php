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

    protected $fillable = [
        'brand_product_id', 'brand_product_variant_id', 'sku', 'product_code',
        'original_price', 'selling_price', 'qty',
    ];

    public function variant_options()
    {
        return $this->hasMany(BrandProductVariantOption::class);
    }
}
