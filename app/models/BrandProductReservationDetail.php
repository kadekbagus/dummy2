<?php

class BrandProductReservationDetail extends Eloquent
{
    protected $primaryKey = 'brand_product_reservation_detail_id';

    protected $table = 'brand_product_reservation_details';

    public function store()
    {
        return $this->hasOne('Tenant', 'merchant_id', 'value');
    }

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
        return $this->belongsTo(BrandProductVariant::class);
    }

    public function reservation()
    {
        return $this->belongsTo(BrandProductReservation::class);
    }
}
