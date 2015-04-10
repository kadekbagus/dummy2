<?php
class TransactionDetailCoupon extends Eloquent
{
    /**
    * Transaction Detail Coupons model
    *
    * @author kadek <kadek@dominopos.com>
    */

    protected $table = 'transaction_detail_coupons';

    protected $primaryKey = 'transaction_detail_coupon_id';

    public function coupon()
    {
        return $this->belongsTo('Coupon', 'promotion_id', 'promotion_id');
    }

    public function transaction()
    {
        return $this->belongsTo('Transaction', 'transaction_id', 'transaction_id');
    }
}