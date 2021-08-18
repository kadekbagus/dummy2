<?php

/**
 * Brand Product Reservation
 */
class BrandProductReservation extends Eloquent
{
    use ModelStatusTrait;

    protected $primaryKey = 'brand_product_reservation_id';

    protected $table = 'brand_product_reservations';

    protected $guarded = [];

    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_CANCELED = 'cancelled';
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

    public function brand_product_variant()
    {
        return $this->belongsTo(BrandProductVariant::class);
    }

    public function store()
    {
        return $this->belongsTo(Tenant::class, 'merchant_id', 'merchant_id');
    }

    public function variants()
    {
        return $this->hasMany(BrandProductReservationDetail::class)
            ->where('option_type', 'variant_option');
    }

    public function image()
    {
        return $this->hasOne(BrandProductReservationDetail::class)
            ->where('option_type', 'image');
    }

    public static function getReservedQuantity($variantId)
    {
        return BrandProductReservation::select('quantity')
            ->join(
                'brand_product_reservation_details',
                'brand_product_reservations.brand_product_reservation_id',
                '=',
                'brand_product_reservation_details.brand_product_reservation_id'
            )
            ->where('brand_product_variant_id', $variantId)
            ->whereIn('status', [
                BrandProductReservation::STATUS_PENDING,
                BrandProductReservation::STATUS_ACCEPTED,
                BrandProductReservation::STATUS_DONE,
            ])
            ->sum('quantity');
    }
}
