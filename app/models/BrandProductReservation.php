<?php

class BrandProductReservation extends Eloquent
{
    protected $primaryKey = 'brand_product_reservation_id';

    protected $table = 'brand_product_reservations';

    public function users() {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function decliner() {
        return $this->belongsTo('BppUser', 'declined_by', 'bpp_user_id');
    }

    public function details() {
        return $this->hasMany('BrandProductReservationDetail', 'brand_product_reservation_id', 'brand_product_reservation_id');
    }
}