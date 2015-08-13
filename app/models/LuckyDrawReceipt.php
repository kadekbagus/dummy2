<?php
class LuckyDrawReceipt extends Eloquent
{
    /**
     * LuckyDrawReceipt Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'lucky_draw_receipts';

    protected $primaryKey = 'lucky_draw_receipt_id';

    public function mall()
    {
        return $this->belongsTo('Retailer', 'mall_id', 'merchant_id')->isMall();
    }

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function receiptRetailer()
    {
        return $this->belongsTo('Retailer', 'receipt_retailer_id', 'merchant_id')->isMall('no');
    }

    public function numbers()
    {
        return $this->belongsToMany('LuckyDrawNumber', 'lucky_draw_number_receipt', 'lucky_draw_receipt_id', 'lucky_draw_number_id');
    }

    /**
     * Relation of table promotions (coupon) with the receipt.
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public function issuedCoupons()
    {
        return $this->belongsToMany('IssuedCoupon', 'lucky_draw_number_receipt', 'lucky_draw_receipt_id', 'issued_coupon_id');
    }

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

}
