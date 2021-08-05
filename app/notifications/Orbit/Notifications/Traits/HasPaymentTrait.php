<?php namespace Orbit\Notifications\Traits;

use Carbon\Carbon;
use Config;
use Discount;
use Orbit\Helper\Notifications\AdminNotification;
use Orbit\Helper\Util\LandingPageUrlGenerator as LandingPageUrlGenerator;

/**
 * A trait that indicate that the using object/model
 * *should* have PaymentTransaction instance as property in it.
 *
 * @author Budi <budi@dominopos.com>
 */
trait HasPaymentTrait
{
    protected $payment = null;

    protected $objectType = 'pulsa';

    protected $paymentMethodMapper = [
        'gopay' => 'GOJEK',
        'dana' => 'Dana',
    ];

    protected $productType = 'default';

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
            'discounts' => [],
            'total'     => $this->payment->getGrandTotal(),
        ];

        foreach($this->payment->details as $item) {
            $detailItem = [
                'name'      => $item->object_name,
                'shortName' => $item->object_name,
                'quantity'  => $item->quantity,
                'price'     => $item->getPrice(),
                'total'     => $item->getTotal(),
            ];

            if ($item->price < 0 || $item->object_type === 'discount') {
                $discount = Discount::select('value_in_percent')->find($item->object_id);
                $discount = ! empty($discount) ? $discount->value_in_percent . '%' : '';
                $detailItem['name'] = $discount;
                $detailItem['quantity'] = '';
                $transaction['discounts'][] = $detailItem;
            } else {
                $detailItem['name'] .= $this->getSerialNumber();
                $transaction['items'][] = $detailItem;
            }
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

    protected function getGameName()
    {
        return $this->payment->game_name;
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
    public function getMyPurchasesUrl($path = '')
    {
        return Config::get('orbit.transaction.my_purchases_url', 'https://gotomalls.com/my/purchases') . $path;
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

    /**
     * Get buy url depends on the purchased item's type.
     * @return [type] [description]
     */
    protected function getBuyUrl()
    {
        $baseUrl = Config::get('orbit.base_landing_page_url', 'https://www.gotomalls.com');
        $paymentDetail = $this->payment->details->first();

        return $baseUrl . LandingPageUrlGenerator::create(
            $paymentDetail->object_type,
            $paymentDetail->object_id,
            $paymentDetail->object_name
        )->generateUrl();
    }

    /**
     * Get purchased object type.
     *
     * @return [type] [description]
     */
    protected function getObjectType()
    {
        foreach($this->payment->details as $detail) {
            if ($detail->object_type !== 'discount') {
                $this->objectType = $detail->object_type;
                break;
            }
        }

        return $this->objectType;
    }

    /**
     * Get serial number from the purchase.
     *
     * @return [type] [description]
     */
    protected function getSerialNumber()
    {
        if (isset($this->serialNumber) && ! empty($this->serialNumber)) {
            return "<br><br>SN: {$this->serialNumber}";
        }

        return '';
    }

    /**
     * Get the payment method, Gopay or Dana.
     *
     * @return [type] [description]
     */
    protected function getPaymentMethod()
    {
        $paymentMethod = '';

        if (! empty($this->payment->midtrans)) {
            $paymentInfo = json_decode(unserialize($this->payment->midtrans->payment_midtrans_info), true);

            if (isset($paymentInfo['payment_type'])) {
                $paymentMethod = $paymentInfo['payment_type'];
                $paymentMethod = isset($this->paymentMethodMapper[$paymentMethod])
                    ? $this->paymentMethodMapper[$paymentMethod] : '';
            }
        }

        return $paymentMethod;
    }

    /**
     * Resolve the type of product being purchased.
     *
     * @return [type] [description]
     */
    protected function resolveProductType()
    {
        foreach($this->payment->details as $detail) {
            if ($detail->object_type !== 'discount') {

                $this->productType = $detail->object_type;

                if (isset($detail->pulsa) && ! empty($detail->pulsa)) {
                    $this->productType = $detail->pulsa->object_type;
                } else if (isset($detail->digital_product) && ! empty($detail->digital_product)) {
                    $this->productType = $detail->digital_product->product_type;
                }

                break;
            }
        }

        return $this->productType;
    }

    /**
     * Get the provider name;
     * @return [type] [description]
     */
    protected function getProviderName()
    {
        $providerName = '';

        foreach($this->payment->details as $detail) {
            if ($detail->object_type !== 'discount') {

                if (isset($detail->pulsa) && ! empty($detail->pulsa)) {
                    $providerName = 'MCash';
                } else if (isset($detail->coupon) && ! empty($detail->coupon)) {
                    $providerName = 'GiftN';
                } else if (isset($detail->provider_product) && ! empty($detail->provider_product)) {
                    $providerName = $detail->provider_product->provider_name;
                }

                break;
            }
        }

        return ucwords($providerName);
    }

    protected function getProductName()
    {
        return $this->payment->details->filter(function($item) {
            return $item->object_type === 'order';
        })->first()->order->details->first()->product_name;
    }
}
