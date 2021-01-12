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
}
