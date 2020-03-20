<?php

class BrandProductReservationDetail extends Eloquent
{
    protected $primaryKey = 'brand_product_reservation_detail_id';

    protected $table = 'brand_product_reservation_details';

    public function stores() {
        return $this->hasOne('Tenant', 'object_id', 'merchant_id')
            ->where('brand_product_reservation_details.object_type', 'merchant');
    }
}