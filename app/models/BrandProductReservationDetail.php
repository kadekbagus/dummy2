<?php

class BrandProductReservationDetail extends Eloquent
{
    protected $primaryKey = 'brand_product_reservation_detail_id';

    protected $table = 'brand_product_reservation_details';

    protected $guarded = [];

    public function media()
    {
        return $this->hasOne(Media::class, 'media_id', 'value');
    }

    public function variant_details()
    {
        return $this->hasMany(BrandProductReservationVariantDetail::class);
    }

    public function product_variant()
    {
        return $this->belongsTo(BrandProductVariant::class, 'brand_product_variant_id', 'brand_product_variant_id');
    }

    public function reservation()
    {
        return $this->belongsTo(BrandProductReservation::class, 'brand_product_reservation_id', 'brand_product_reservation_id');
    }
}
