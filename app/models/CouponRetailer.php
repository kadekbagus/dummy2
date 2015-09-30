<?php

class CouponRetailer extends Eloquent
{
    /**
     * CouponRetailer Model
     *
     * @author Tian <tian@dominopos.com>
     */

    protected $primaryKey = 'promotion_retailer_id';

    protected $table = 'promotion_retailer';

    public function coupon()
    {
        return $this->belongsTo('Coupon', 'promotion_id', 'promotion_id');
    }

    public function tenant()
    {
        return $this->belongsTo('Tenant', 'retailer_id', 'merchant_id');
    }
}
