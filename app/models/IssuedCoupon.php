<?php
class IssuedCoupon extends Eloquent
{
    /**
     * IssuedCoupon Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    const ISSUE_COUPON_INCREMENT = 111111;

    protected $table = 'issued_coupons';

    protected $primaryKey = 'issued_coupon_id';

    public function coupon()
    {
        return $this->belongsTo('Coupon', 'promotion_id', 'promotion_id');
    }

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function issuerretailer()
    {
        return $this->belongsTo('Retailer', 'issuer_retailer_id', 'merchant_id');
    }

}
