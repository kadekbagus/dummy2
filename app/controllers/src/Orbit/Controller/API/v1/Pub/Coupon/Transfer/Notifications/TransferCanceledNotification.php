<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer\Notifications;

use Orbit\Controller\API\v1\Pub\Coupon\Transfer\Notifications\CouponTransferNotification;

/**
 * Notify recipient that the transfer was canceled by owner.
 *
 * @author Budi <budi@dominopos.com>
 */
class TransferCanceledNotification extends CouponTransferNotification
{
    protected $signature = 'transfer-canceled';

    protected $logID = 'TransferCanceledNotification';

    public function getRecipientEmail()
    {
        return $this->notifiable->email;
    }

    /**
     * Get the email templates that will be used.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.coupon.transfer.transfer-canceled',
            'text' => 'emails.coupon.transfer.transfer-canceled-text',
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
            'emailSubject'      => 'Coupon Transfer Canceled',
            'couponOwnerName'   => $this->issuedCoupon->user->getFullName(),
        ]);
    }
}
