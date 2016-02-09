<?php

class CouponRetailerRedeem extends Eloquent
{
    /**
     * CouponRetailerRedeem Model
     *
     * @author Tian <tian@dominopos.com>
     */

    protected $primaryKey = 'promotion_retailer_redeem_id';

    protected $table = 'promotion_retailer_redeem';

    public function coupon()
    {
        return $this->belongsTo('Coupon', 'promotion_id', 'promotion_id');
    }

    public function tenant()
    {
        return $this->belongsTo('Tenant', 'retailer_id', 'merchant_id');
    }
}
