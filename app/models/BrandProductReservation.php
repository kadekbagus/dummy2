<?php

/**
 * Brand Product Reservation
 */
class BrandProductReservation extends Eloquent
{
    protected $table = 'brand_product_reservations';

    protected $primaryKey = 'brand_product_reservation_id';

    public function consumer()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class);
    }

    public function variant()
    {
        return $this->hasOne(BrandProductVariant::class);
    }
}
