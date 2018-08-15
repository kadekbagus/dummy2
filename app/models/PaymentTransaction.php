<?php

// use Orbit\Helper\Presenters\Presentable;

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

    // use Presentable;

    // protected $presenter = 'Orbit\\Presenters\\Payment\\TransactionPresenter';

    const STATUS_STARTING           = 'starting';
    const STATUS_PENDING            = 'pending';
    const STATUS_FAILED             = 'failed';
    const STATUS_EXPIRED            = 'expired';
    const STATUS_SUCCESS            = 'success';
    const STATUS_DENIED             = 'denied';
    const STATUS_SUSPICIOUS         = 'suspicious';

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
     * @return [type] [description]
     */
    public function coupon_sepulsa()
    {
        return $this->belongsTo('CouponSepulsa', 'object_id', 'promotion_id');
    }

    /**
     * Payment - Coupon relation.
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
     * Payment - IssuedCoupon relation.
     *
     * @return [type] [description]
     */
    public function issued_coupons()
    {
        return $this->hasMany('IssuedCoupon', 'transaction_id');
    }

    /**
     * Payment - User relation.
     *
     * @return [type] [description]
     */
    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function items()
    {
        return $this->hasMany('PaymentTransactionDetail');
    }

    public function details()
    {
        return $this->hasMany('PaymentTransactionDetail');
    }

    public function midtrans()
    {
        return $this->hasOne('PaymentMidtrans');
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
        if (! empty($this->details)) {
            return $this->details->first()->coupon->promotion_type === Coupon::TYPE_SEPULSA;
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
        if (! empty($this->details)) {
            return $this->details->first()->coupon->promotion_type === Coupon::TYPE_HOT_DEALS;
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
     * Get transaction date which should be based on mall location.
     *
     * @param  string $format [description]
     * @return [type]         [description]
     */
    public function getTransactionDate($format = 'j M Y')
    {
        if (! empty($this->timezone_name)) {
            return $this->created_at->timezone($this->timezone_name)->format($format);
        }

        return $this->created_at->format($format);
    }

    /**
     * Get formatted grand total with currency code.
     *
     * @return string
     */
    public function getGrandTotal()
    {
        $grandTotal = 0.0;
        foreach($this->details as $item) {
            $grandTotal += $item->quantity * $item->price;
        }

        return $this->currency . ' ' . number_format($grandTotal, 0, ',', '.');
    }

    /**
     * Clean up anything related to this payment.
     * If payment expired, failed, etc, it should reset/remove any related issued coupon.
     *
     * Should be called ONLY after checking if payment is expired, failed, or denied.
     *
     * @todo  use the same method makeAvailable() to take care of Sepulsa voucher (unify it in makeAvailable())
     * @return [type] [description]
     */
    public function cleanUp()
    {
        Log::info('Payment: Cleaning up payment... TransactionID: ' . $this->payment_transaction_id . ', current status: ' . $this->status);

        $issuedCoupons = $this->issued_coupons;

        if (empty($issuedCoupons)) {
            $issuedCoupons = IssuedCoupon::where('transaction_id', $this->payment_transaction_id)->get();

            if (empty($issuedCoupons)) {
                Log::info('Payment: Transaction ID ' . $this->payment_transaction_id . '. Related issuedCoupon not found. Nothing to do.');
                return;
            }
        }

        // If it is Sepulsa, then remove the IssuedCoupon record.
        if ($this->forSepulsa()) {
            foreach($issuedCoupons as $issuedCoupon) {
                // TODO: Check if the coupon is already issued. If so, then what should we do?
                if ($issuedCoupon->status === IssuedCoupon::STATUS_RESERVED) {
                    Log::info('Payment: Transaction ID ' . $this->payment_transaction_id . '. Removing reserved sepulsa voucher.');
                    IssuedCoupon::where('issued_coupon_id', $issuedCoupon->issued_coupon_id)->delete();
                }
                else {
                    Log::info('Payment: Transaction ID ' . $this->payment_transaction_id . '. Voucher is already issued. Do NOTHING at the moment.');
                }
            }
        }
        // If it is Hot Deals, then reset the IssuedCoupon state.
        else if ($this->forHotDeals()) {
            Log::info('Payment: Transaction ID ' . $this->payment_transaction_id . '. Reverting reserved hot deals coupon status.');

            foreach($issuedCoupons as $issuedCoupon) {
                $issuedCoupon->makeAvailable();
            }

            Log::info('Payment: hot deals coupon reverted. IssuedCoupon ID: ' . $issuedCoupon->issued_coupon_id);
        }

        // Update the availability...
        Coupon::findOnWriteConnection($issuedCoupons->first()->promotion_id)->updateAvailability();
    }

    /**
     * Determine if the payment type is match certain type.
     *
     * @param  array  $paymentTypes [description]
     * @return [type]               [description]
     */
    public function paidWith($paymentTypes = [])
    {
        $paymentInfo = json_decode(unserialize($this->midtrans->payment_midtrans_info));
        if (! empty($paymentInfo)) {
            if (in_array($paymentInfo->payment_type, $paymentTypes)) {
                return true;
            }
        }

        return false;
    }
}
