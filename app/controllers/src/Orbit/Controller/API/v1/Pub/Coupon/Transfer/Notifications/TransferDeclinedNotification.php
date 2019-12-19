<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer\Notifications;

use Illuminate\Support\Facades\Config;
use Orbit\Controller\API\v1\Pub\Coupon\Transfer\Notifications\CouponTransferNotification;

/**
 * Notify coupon owner that the transfer was declined.
 *
 * @author Budi <budi@dominopos.com>
 */
class TransferDeclinedNotification extends CouponTransferNotification
{
    protected $signature = 'transfer-declined';

    protected $logID = 'TransferDeclinedNotification';

    public function getRecipientEmail()
    {
        return $this->notifiable->user_email;
    }

    /**
     * Get the email templates that will be used.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.coupon.transfer.transfer-declined',
            'text' => 'emails.coupon.transfer.transfer-declined-text',
        ];
    }

    /**
     * Get my wallet url.
     * @return [type] [description]
     */
    private function getMyWalletUrl()
    {
        return Config::get('orbit.coupon.direct_redemption_url');
    }

    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        $coupon = $this->issuedCoupon->coupon;
        return array_merge(parent::getEmailData(), [
            'header'            => trans('email-transfer.header'),
            'greeting'          => trans('email-transfer.declined.greeting', ['ownerName' => $this->issuedCoupon->user->getFullName()]),
            'emailSubject'      => trans('email-transfer.declined.subject'),
            'body'              => trans('email-transfer.declined.message', ['recipientName' => $this->recipientName]),
            'myWalletUrl'       => $this->getMyWalletUrl(),
            'btnOpenWallet'     => trans('email-transfer.declined.btn_open_my_wallet'),
            'couponId'          => $coupon->promotion_id,
            'couponName'        => $coupon->promotion_name,
            'couponUrl'         => $this->getCouponUrl($coupon->promotion_id, $coupon->promotion_name),
            'couponImage'       => $this->getImageUrl($coupon->promotion_id),
            'brandName'         => $this->getBrand($coupon->promotion_id),
        ]);
    }
}
