<?php

/**
 * Brand Product Variant Option
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductVariantOption extends Eloquent
{
    protected $table = 'brand_product_variant_options';

    protected $primaryKey = 'brand_product_variant_option_id';

    protected $fillable = [
        'brand_product_variant_option_id', 'option_type', 'option_id'
    ];
}
