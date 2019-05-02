<?php namespace Orbit\Notifications\Traits;

use Config;
use Carbon\Carbon;

/**
 * A trait that indicate that the using object/model
 * *should* have PaymentTransaction instance as property in it.
 *
 * @author Budi <budi@dominopos.com>
 */
trait HasPaymentTrait
{
    protected $payment = null;

    /**
     * Get the transaction data.
     *
     * @todo  return transaction as object instead of array. (need to adjust the view/email templates)
     * @todo  use presenter helper.
     *
     * @return [type] [description]
     */
    protected function getTransactionData()
    {
        $transaction = [
            'id'        => $this->payment->payment_transaction_id,
            'date'      => $this->payment->getTransactionDate(),
            'customer'  => $this->getCustomerData(),
            'items'     => [],
            'total'     => $this->payment->getGrandTotal(),
        ];

        foreach($this->payment->details as $item) {
            $transaction['items'][] = [
                'name'      => $item->object_name,
                'quantity'  => $item->quantity,
                'price'     => $item->getPrice(),
                'total'     => $item->getTotal(),
            ];
        }

        return $transaction;
    }

    protected function getCustomerEmail()
    {
        return $this->payment->user_email;
    }

    protected function getCustomerName()
    {
        return $this->payment->user_name;
    }

    protected function getCustomerPhone()
    {
        return $this->payment->phone;
    }

    /**
     * Get the customer data.
     *
     * @return [type] [description]
     */
    protected function getCustomerData()
    {
        return (object) [
            'email'     => $this->getCustomerEmail(),
            'name'      => $this->getCustomerName(),
            'phone'     => $this->getCustomerPhone(),
        ];
    }

    /**
     * Get the Payment info.
     *
     * @return [type] [description]
     */
    protected function getPaymentInfo()
    {
        $paymentMethod = [];

        if (! empty($this->payment->midtrans) && $this->payment->paidWith(['echannel', 'bank_transfer'])) {
            $paymentMethod = json_decode(unserialize($this->payment->midtrans->payment_midtrans_info), true);
        }

        return $paymentMethod;
    }

    /**
     * Get the approximate expiration date and time of the transaction.
     * Always use Asia/Jakarta (GMT +7) because we can not determine exactly which
     * timezone was used by Customer when making the purchase.
     *
     * @return [type] [description]
     */
    public function getPaymentExpirationDate()
    {
        $expiredIn = Config::get('orbit.partners_api.midtrans.expired_in', 1440);
        if ($this->payment->paidWith(['gopay'])) {
            $expiredIn = Config::get('orbit.partners_api.midtrans.gopay_expired_in', $expiredIn);
        }

        return $this->payment->created_at->timezone('Asia/Jakarta')->addMinutes($expiredIn)->format('d F Y, H:i') . ' WIB (GMT +7)';
    }

    /**
     * Generate cancel url.
     *
     * @return [type] [description]
     */
    public function getCancelUrl()
    {
        return sprintf(Config::get('orbit.transaction.cancel_purchase_url'), $this->payment->payment_transaction_id);
    }

    /**
     * Get the url for button My Wallet.
     *
     * @return [type] [description]
     */
    public function getMyWalletUrl()
    {
        return Config::get('orbit.coupon.direct_redemption_url');
    }

    /**
     * Get the url for button My Purchases.
     *
     * @return [type] [description]
     */
    public function getMyPurchasesUrl()
    {
        return Config::get('orbit.transaction.my_purchases_url', 'https://gotomalls.com/my/purchases');
    }

    /**
     * Get Coupon expiration date.
     *
     * @return [type] [description]
     */
    protected function getCouponExpiredDate($format = 'j M Y')
    {
        if ($this->payment->issued_coupons->count() > 0) {
            return Carbon::parse($this->payment->issued_coupons->first()->expired_date)->format($format);
        }

        return '-';
    }
}
