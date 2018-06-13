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

    const STATUS_STARTING           = 'starting';
    const STATUS_PENDING            = 'pending';
    const STATUS_FAILED             = 'failed';
    const STATUS_SUCCESS            = 'success';

    // Status 'success_no_coupon' means the payment was success but we can not get/take the coupon from Sepulsa API
    // either it is not available (all taken) or inactive.
    const STATUS_SUCCESS_NO_COUPON  = 'success_no_coupon';

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
     * @todo  use proper status to indicate completed payment. At the moment these statuses are assumption.
     * @return [type] [description]
     */
    public function completed()
    {
        return in_array($this->status, [
            self::STATUS_SUCCESS, 
            self::STATUS_SUCCESS_NO_COUPON, 
            'success_no_coup', // @todo should be removed.
        ]);
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
