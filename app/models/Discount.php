<?php

class Discount extends Eloquent
{
    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $primaryKey = 'discount_id';

    public function linkedObjects()
    {
        return $this->hasMany(ObjectDiscount::class);
    }

    public function coupons()
    {
        return $this->linkedObjects()->where('object_type', 'coupon');
    }

    public function scopeBetweenExpiryDate($query)
    {
        $currentDatetimeUTC = gmdate('Y-m-d H:i:s');
        return $query->where('start_date', '<=', $currentDatetimeUTC)
            ->where('end_date', '>=', $currentDatetimeUTC);
    }
}
