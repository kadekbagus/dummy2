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
        'original_price', 'selling_price', 'quantity',
        'created_by'
    ];

    public function variant_options()
    {
        return $this->hasMany(BrandProductVariantOption::class)
            ->orderBy('brand_product_variant_options.option_type', 'desc');
    }

    public function brand_product()
    {
        return $this->belongsTo(BrandProduct::class);
    }

    public function reservations()
    {
        return $this->hasMany(BrandProductReservation::class);
    }

    public function reservation_details()
    {
        return $this->hasMany(BrandProductReservationDetail::class);
    }

    public function order_details()
    {
        return $this->hasMany(OrderDetail::class);
    }
}
