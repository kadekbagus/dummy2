<?php

class PromoCode extends Eloquent
{
    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'discounts';
    protected $primaryKey = 'discount_id';

    public function linkedObjects()
    {
        return $this->hasMany(PromoCodeLinkedObject::class, 'discount_id');
    }

    public function coupons()
    {
        return $this->linkedObjects()->where('object_type', 'coupon');
    }

    public function users()
    {
        return $this->belongsTo(User::class);
    }
}
