<?php namespace Orbit\Notifications\Coupon;

use Orbit\Helper\Notifications\Notification;
use Orbit\Helper\Util\JobBurier;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Orbit\Helper\Security\Encrypter;
use Carbon\Carbon;

use Mail;
use Config;
use Log;
use Queue;
use Exception;

/**
 * IssuedCoupon Notification. 
 * Applicable for Sepulsa and Hot Deals.
 * 
 */
class IssuedCouponNotification extends Notification
{
    protected $issuedCoupon = null;

    protected $payment = null;

    function __construct($issuedCoupon, $payment = null)
    {
        $this->issuedCoupon = $issuedCoupon;
        $this->payment      = $payment;
        $this->queueName    = Config::get('orbit.registration.mobile.queue_name');

        if (empty($this->payment)) {
            $this->issuedCoupon->load('payment');
            $this->payment = $this->issuedCoupon->payment;
        }
    }

    /**
     * Custom field email address if not reading from field 'email'
     * 
     * @return [type] [description]
     */
    protected function getEmailAddress()
    {
        return $this->issuedCoupon->user_email;
    }

    /**
     * Get the email data.
     * Notice that we generate redeemUrl here. So anytime we need to send (or resend)
     * the IssuedCoupon, we use the latest url format from config.
     *
     * Alternatively, would be storing the redeemUrl once user claim the coupon. No need to generate it everytime 
     * we want to send it.
     * 
     * @return [type] [description]
     */
    protected function getEmailData()
    {
        $redeemUrl = '';

        // If sepulsa then use url from the issuedCoupon url..
        if ($this->payment->forSepulsa()) {
            $redeemUrl = $this->issuedCoupon->url;
        }
        else if ($this->payment->forHotDeals()) {
            // If hotdeals, create redeem url from config...
            $encryptionKey = Config::get('orbit.security.encryption_key');
            $encryptionDriver = Config::get('orbit.security.encryption_driver');
            $encrypter = new Encrypter($encryptionKey, $encryptionDriver);

            $hashedIssuedCouponCid = rawurlencode($encrypter->encrypt($this->issuedCoupon->promotion_id));
            $hashedIssuedCouponUid = rawurlencode($encrypter->encrypt($this->issuedCoupon->user_email));

            // cid=%s&uid=%s
            $redeemUrl = sprintf(Config::get('orbit.coupon.direct_redemption_url'), $hashedIssuedCouponCid, $hashedIssuedCouponUid);
        }

        if (empty($redeemUrl)) {
            \Log::debug('Coupon claimed, but we unable to send issued coupon via email. Empty redeem url.');
        }

        return [
            'redeem_url'        => $redeemUrl,
            'coupon_id'         => $this->issuedCoupon->promotion_id,
            'email'             => $this->getEmailAddress(),
        ];
    }

    /**
     * Send notification.
     * 
     * @return [type] [description]
     */
    public function send()
    {
        // Use Queue that we already have.
        Queue::push(
            'Orbit\\Queue\\IssuedCouponMailQueue',
            $this->getEmailData(),
            $this->queueName
        );

        // Other notification method can be added here...
    }
}
