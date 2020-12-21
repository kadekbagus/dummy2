<?php

/**
 * Brand Product Reservation
 */
class BrandProductReservation extends Eloquent
{
    protected $primaryKey = 'brand_product_reservation_id';

    protected $table = 'brand_product_reservations';

    const STATUS_NEW = 'new';
    const STATUS_CANCELED = 'canceled';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_DECLINED = 'declined';
    const STATUS_DONE = 'done';
    const STATUS_EXPIRED = 'expired';

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
