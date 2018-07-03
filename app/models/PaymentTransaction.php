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
    const STATUS_EXPIRED            = 'expired';
    const STATUS_SUCCESS            = 'success';
    const STATUS_DENIED             = 'denied';

    /**
     * It means we are in the process of getting coupon/voucher from Sepulsa.
     * This status specific for Sepulsa Deals only.
     */
    const STATUS_SUCCESS_NO_COUPON  = 'success_no_coupon';

    /**
     * It means system can not get the voucher or after trying for a few times for Sepulsa).
     */
    const STATUS_SUCCESS_NO_COUPON_FAILED = 'success_no_coupon_failed';

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
     * @return [type] [description]
     */
    public function completed()
    {
        return in_array($this->status, [
            self::STATUS_SUCCESS,
            self::STATUS_SUCCESS_NO_COUPON,
            self::STATUS_SUCCESS_NO_COUPON_FAILED,
        ]);
    }

    /**
     * Determine if the payment is expired or not.
     *
     * @author Budi <budi@dominopos.com>
     *
     * @return [type] [description]
     */
    public function expired()
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    /**
     * Determine if the payment is failed or not.
     *
     * @return [type] [description]
     */
    public function pending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Determine if the payment is failed or not.
     * 
     * @return [type] [description]
     */
    public function failed()
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Determine if the payment is denied or not.
     *
     * @return [type] [description]
     */
    public function denied()
    {
        return $this->status === self::STATUS_DENIED;
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
            return $this->coupon->promotion_type === Coupon::TYPE_SEPULSA;
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
            return $this->coupon->promotion_type === Coupon::TYPE_HOT_DEALS;
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

    /**
     * Clean up anything related to this payment. 
     * If payment expired, failed, etc, it should reset/remove any related issued coupon.
     *
     * Should be called ONLY after checking if payment is expired, failed, or denied.
     * 
     * @return [type] [description]
     */
    public function cleanUp()
    {
        Log::info('Payment: Cleaning up payment... TransactionID: ' . $this->payment_transaction_id . ', current status: ' . $this->status);

        // If it is Sepulsa, then remove the IssuedCoupon record.
        if ($this->forSepulsa()) {
            Log::info('Payment: Transaction ID ' . $this->payment_transaction_id . '. Removing issued sepulsa coupon.');

            IssuedCoupon::where('transaction_id', $this->payment_transaction_id)->delete();
        }
        // If it is Hot Deals, then reset the IssuedCoupon state.
        else if ($this->forHotDeals()) {
            Log::info('Payment: Transaction ID ' . $this->payment_transaction_id . '. Reverting issued hot deals coupon status.');

            $issuedCoupon = $this->issued_coupon;
            if (empty($issuedCoupon)) {
                $issuedCoupon = IssuedCoupon::where('transaction_id', $this->payment_transaction_id)->first();
            }

            if (! empty($issuedCoupon)) {
                $issuedCoupon->makeAvailable();
            }
        }

        // Update the availability...
        if (! empty($this->coupon)) {
            $this->coupon->updateAvailability();
        }
    }
}
