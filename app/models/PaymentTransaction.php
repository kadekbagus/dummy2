<?php

use Orbit\Helper\AutoIssueCoupon\HasRewards;

// use Orbit\Helper\Presenters\Presentable;

class PaymentTransaction extends Eloquent
{
    /**
     * Payment transcations Model
     * Saving payment transactios
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */

    use ModelStatusTrait,
        HasRewards;

    protected $primaryKey = 'payment_transaction_id';

    protected $table = 'payment_transactions';

    protected $guarded = [];

    // use Presentable;

    // protected $presenter = 'Orbit\\Presenters\\Payment\\TransactionPresenter';

    const STATUS_STARTING           = 'starting';
    const STATUS_PENDING            = 'pending';
    const STATUS_FAILED             = 'failed';
    const STATUS_EXPIRED            = 'expired';
    const STATUS_SUCCESS            = 'success';
    const STATUS_DENIED             = 'denied';
    const STATUS_SUSPICIOUS         = 'suspicious';
    const STATUS_CANCELED           = 'canceled';
    const STATUS_ABORTED            = 'abort';
    const STATUS_REFUND             = 'refund';

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
     * Status when preparing pulsa..
     */
    const STATUS_SUCCESS_NO_PULSA = 'success_no_pulsa';

    /**
     * Status when failed getting pulsa from provider.
     */
    const STATUS_SUCCESS_NO_PULSA_FAILED = 'success_no_pulsa_failed';

    /**
     * Indicate that the payment of this transaction was refunded.
     */
    const STATUS_SUCCESS_REFUND = 'success_refund';

    /**
     * Indicate that the payment is success but still waiting/processing product purchase from vendor/provider.
     * @todo  should ONLY use this instead of different status for each type (STATUS_SUCCESS_NO_PULSA, STATUS_SUCCESS_NO_COUPON)
     */
    const STATUS_SUCCESS_NO_PRODUCT = 'success_no_product';

    /**
     * Indicate that payment is success but product purchase from vendor/provider is failed.
     * @todo should ONLY use this for all instead of different status for each type (STATUS_SUCCESS_NO_PULSA_FAILED, STATUS_SUCCESS_NO_COUPON_FAILED)
     */
    const STATUS_SUCCESS_NO_PRODUCT_FAILED = 'success_no_product_failed';

    /**
     * Make promo_code as property, to avoid SQL error when saving.
     */
    public $promo_code = null;

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
     * Payment Refund.
     *
     * @return [type] [description]
     */
    public function refunds()
    {
        return $this->hasMany('PaymentTransaction', 'parent_id');
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
        return $this->hasMany('PaymentTransactionDetail')->oldest();
    }

    public function discount()
    {
        return $this->hasOne('PaymentTransactionDetail')->where('object_type', 'discount');
    }

    public function discount_code()
    {
        return $this->hasOne('DiscountCode', 'payment_transaction_id');
    }

    public function discount_codes()
    {
        return $this->hasMany('DiscountCode', 'payment_transaction_id');
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
            self::STATUS_SUCCESS_NO_PULSA,
            self::STATUS_SUCCESS_NO_PULSA_FAILED,
            self::STATUS_SUCCESS_NO_PRODUCT,
            self::STATUS_SUCCESS_NO_PRODUCT_FAILED,
            self::STATUS_SUCCESS_REFUND,
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
     * Determine if the payment is canceled/aborted or not.
     *
     * @return [type] [description]
     */
    public function canceled()
    {
        return $this->status === self::STATUS_CANCELED || $this->aborted();
    }

    /**
     * Determine if the payment was aborted or not.
     *
     * @return [type] [description]
     */
    public function aborted()
    {
        return $this->status === self::STATUS_ABORTED;
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
        if ($this->details->count() > 0 && ! empty($this->details->first()->coupon)) {
            return $this->details->first()->coupon->promotion_type === Coupon::TYPE_SEPULSA;
        }

        return false;
    }

    /**
     * Determine if the payment is for Pulsa or Data Plan.
     *
     * @author Budi <budi@dominopos.com>
     *
     * @return [type] [description]
     */
    public function forPulsa()
    {
        foreach($this->details as $detail) {
            if (! empty($detail->pulsa)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the payment is for Digital Product
     *
     * @author Budi <budi@dominopos.com>
     *
     * @return [type] [description]
     */
    public function forDigitalProduct()
    {
        foreach($this->details as $detail) {
            if (! empty($detail->digital_product)) {
                return true;
            }
        }

        return false;
    }

    public function forUPoint($method = '')
    {
        foreach($this->details as $detail) {
            if (! empty($detail->provider_product)) {
                return stripos($detail->provider_product->provider_name, "upoint-{$method}") !== false;
            }
        }

        return false;
    }

    public function forWoodoos()
    {
        foreach($this->details as $detail) {
            if (! empty($detail->provider_product)) {
                return $detail->provider_product->provider_name === "woodoos";
            }
        }

        return false;
    }

    public function forMCashElectricity()
    {
        foreach($this->details as $detail) {
            if (! empty($detail->provider_product)) {
                return $detail->provider_product->provider_name === 'mcash'
                    && $detail->provider_product->product_type === 'electricity';
            }
        }

        return false;
    }

    /**
     * Determine if the payment is for Pulsa.
     *
     * @author Budi <budi@dominopos.com>
     *
     * @return [type] [description]
     */
    public function forGiftNCoupon()
    {
        foreach($this->details as $detail) {
            if (! empty($detail->coupon) && $detail->coupon->promotion_type === Coupon::TYPE_GIFTNCOUPON) {
                return true;
            }
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
        foreach($this->details as $detail) {
            if (! empty($detail->coupon) && $detail->coupon->promotion_type === Coupon::TYPE_HOT_DEALS) {
                return true;
            }
        }

        return false;
    }

    public function forHotDealsOrGiftN()
    {
        return $this->forHotDeals() || $this->forGiftNCoupon();
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
    public function getTransactionDate($format = 'j M Y, H:i')
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

        if ($this->currency === 'IDR') {
            return $this->currency . ' ' . number_format($grandTotal, 0, ',', '.');
        }

        return $this->currency . ' ' . number_format($grandTotal, 2, '.', ',');
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
            return;
        }

        if ($issuedCoupons->count() === 0) {
            $issuedCoupons = IssuedCoupon::where('transaction_id', $this->payment_transaction_id)->get();

            if ($issuedCoupons->count() === 0) {
                Log::info('Payment: Transaction ID ' . $this->payment_transaction_id . '. Related issuedCoupon not found. Nothing to do.');
                return;
            }
        }

        $couponId = $issuedCoupons->first()->promotion_id;

        // If it is Sepulsa, then remove the IssuedCoupon record.
        // If it is Hot Deals, then reset the IssuedCoupon state.
        if ($this->forSepulsa()) {
            foreach($issuedCoupons as $issuedCoupon) {
                // TODO: Check if the coupon is already issued. If so, then what should we do?
                if ($issuedCoupon->status === IssuedCoupon::STATUS_RESERVED) {
                    Log::info('Payment: Transaction ID ' . $this->payment_transaction_id . '. Removing reserved sepulsa voucher.');

                    // Manual query for each IssuedCoupon
                    IssuedCoupon::where('issued_coupon_id', $issuedCoupon->issued_coupon_id)->delete(TRUE);
                }
                else {
                    Log::info('Payment: Transaction ID ' . $this->payment_transaction_id . '. Voucher is already issued. Do NOTHING at the moment.');
                }
            }
        }
        else if ($this->forHotDealsOrGiftN()) {
            Log::info('Payment: Transaction ID ' . $this->payment_transaction_id . '. Reverting reserved hot deals/gift n coupon status.');

            foreach($issuedCoupons as $issuedCoupon) {
                $issuedCoupon->makeAvailable();
                Log::info('Payment: hot deals/giftn coupon reverted. IssuedCoupon ID: ' . $issuedCoupon->issued_coupon_id);
            }
        }

        // Update the availability...
        Coupon::find($couponId)->updateAvailability();
    }

    /**
     * [detachDiscount description]
     * @return [type] [description]
     */
    public function resetDiscount()
    {
        if (! isset($this->discount_codes)) {
            $this->load('discount_codes');
        }

        foreach($this->discount_codes as $discountCode) {
            $discountCode->makeAvailable();
        }
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

    /**
     * Try to record midtrans refund to our DB
     * by creating special child transaction(s) with negative amount.
     *
     * @return [type] [description]
     */
    public function recordRefund($refundData)
    {
        Log::info("Payment: Refunding payment {$this->payment_transaction_id} ...");

        // List of refund that will be recorded/added to our DB.
        $refundList = [];

        // Loop thru midtrans refund list and see if we already record it.
        foreach($refundData->refunds as $midtransRefund) {

            // A flag to indicate if current midtrans refund item in the loop
            // is already recorded in our DB.
            $recorded = false;

            // Check if we already record the refund in our db.
            foreach($this->refunds as $gtmRefund) {
                if ($gtmRefund->external_payment_transaction_id === $midtransRefund->refund_key) {
                    $recorded = true;
                    break;
                }
            }

            // If not recorded yet, then add it to refundList.
            if (! $recorded && isset($midtransRefund->bank_confirmed_at)) {
                $refundList[] = $midtransRefund;
            }
        }

        // Store new refund record if needed.
        if (count($refundList) > 0) {
            $currentlyRefunded = 0;
            foreach($refundList as $midtransRefund) {
                Log::info("Payment: Recording new refund... ID: {$midtransRefund->refund_key} .. AMOUNT: {$midtransRefund->refund_amount}...");

                $refundedPayment = new PaymentTransaction;
                $refundedPayment->external_payment_transaction_id = $midtransRefund->refund_key;
                $refundedPayment->user_email = $this->user_email;
                $refundedPayment->user_name = $this->user_name;
                $refundedPayment->user_id = $this->user_id;
                $refundedPayment->phone = $this->phone; // phone
                $refundedPayment->country_id = $this->country_id; // country
                $refundedPayment->amount = $midtransRefund->refund_amount * -1;
                $refundedPayment->parent_id = $this->payment_transaction_id;
                $refundedPayment->status = PaymentTransaction::STATUS_REFUND;
                $refundedPayment->timezone_name = $this->timezone_name;
                $refundedPayment->payment_method = $this->payment_method;
                $refundedPayment->currency = $this->currency;
                $refundedPayment->extra_data = $this->extra_data;
                $refundedPayment->provider_response_message = json_encode([
                    'key' => $midtransRefund->refund_key,
                    'amount' => $midtransRefund->refund_amount,
                    'reason' => isset($midtransRefund->reason) ? $midtransRefund->reason : '',
                ]);
                $refundedPayment->save();
                $currentlyRefunded += $midtransRefund->refund_amount;
            }

            Log::info("Payment: CURRENT REFUND AMOUNT: {$currentlyRefunded}");
            Log::info("Payment: TOTAL REFUND AMOUNT: {$refundData->refund_amount}");
        }
        else {
            Log::info("Payment: No refund will be recorded.");
        }

        return $refundList;
    }

    /**
     * Resolve purchased object type.
     *
     * @return [type] [description]
     */
    public function getProductType()
    {
        $productType = null;
        $availableProductType = [
            'coupon',
            'pulsa',
            'data_plan',
            'digital_product',
        ];

        foreach($this->details as $detail) {
            if (in_array($detail->object_type, $availableProductType)) {
                $productType = $detail->object_type;
                break;
            }
        }

        return $productType;
    }

    public function getDigitalProduct()
    {
        $digitalProduct = null;
        foreach($this->details as $detail) {
            if (! empty($detail->digital_product)) {
                $digitalProduct = $detail->digital_product;
                break;
            }
        }

        return $digitalProduct;
    }

    public function getProviderProduct()
    {
        $providerProduct = null;
        foreach($this->details as $detail) {
            if (! empty($detail->provider_product)) {
                $providerProduct = $detail->provider_product;
                break;
            }
        }

        return $providerProduct;
    }
}
