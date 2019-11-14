<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer\Notifications;

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
     * Get the email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        return array_merge(parent::getEmailData(), [
            'emailSubject'      => 'Coupon Transfer Declined',
            'couponOwnerName'   => $this->issuedCoupon->user->getFullName(),
        ]);
    }
}
