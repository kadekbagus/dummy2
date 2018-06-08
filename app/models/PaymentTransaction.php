<?php

class PaymentTransaction extends Eloquent
{
    /**
     * Payment transcations Model
     * Saving payment transactios
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */

    use ModelStatusTrait;

    protected $primaryKey = 'payment_transaction_id';

    protected $table = 'payment_transactions';

    /**
     * Payment - Coupon Sepulsa relation.
     *
     * @author Budi <budi@dominopos.com>
     * 
     * @return [type] [description]
     */
    public function coupon_sepulsa()
    {
        return $this->belongsTo('CouponSepulsa', 'object_id', 'promotion_id');
    }

    /**
     * Payment - Coupon relation.
     *
     * @author Budi <budi@dominopos.com>
     * 
     * @return [type] [description]
     */
    public function coupon()
    {
        return $this->belongsTo('Coupon', 'object_id', 'promotion_id');
    }

    /**
     * Payment - IssuedCoupon relation.
     * 
     * @return [type] [description]
     */
    public function issued_coupon()
    {
        return $this->hasOne('IssuedCoupon', 'transaction_id');
    }

    /**
     * Payment - User relation.
     *
     * @author Budi <budi@dominopos.com>
     * 
     * @return [type] [description]
     */
    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    /**
     * Determine if the payment is fully completed or not.
     *
     * @author Budi <budi@dominopos.com>
     *
     * @todo  use proper status for indicating completed payment. At the moment these statuses are assumption.
     * @return [type] [description]
     */
    public function completed()
    {
        return in_array($this->status, ['success', 'success_no_coupon', 'paid', 'settlement']);
    }

    /**
     * Determine if the payment is for Sepulsa Deals.
     *
     * @author Budi <budi@dominopos.com>
     * 
     * @return [type] [description]
     */
    public function forSepulsa()
    {
        if (! empty($this->coupon)) {
            return $this->coupon->promotion_type === 'sepulsa';
        }

        return false;
    }

    /**
     * Determine if the coupon related to this payment is issued.
     * For sepulsa: 
     *     It means the TakeVoucher request is success and we created the IssuedCoupon record in DB.
     *     
     * @return [type] [description]
     */
    public function couponIssued()
    {
        return ! empty($this->issued_coupon);
    }

    /**
     * Determine if the payment is for Hot Deals.
     *
     * @author Budi <budi@dominopos.com>
     * 
     * @return [type] [description]
     */
    public function forHotDeals()
    {
        if (! empty($this->coupon)) {
            return $this->coupon->promotion_type === 'hot_deals';
        }

        return false;
    }

    /**
     * Get formatted amount.
     * 
     * @return [type] [description]
     */
    public function getAmount()
    {
        return $this->currency . ' ' . number_format($this->amount, 0, ',', '.');
    }
}
