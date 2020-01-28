<?php namespace Orbit\Notifications\DigitalProduct;

use Orbit\Notifications\Payment\ExpiredPaymentNotification as BaseNotification;

/**
 * Email notification for Expired Payment (Pulsa).
 *
 * @author Budi <budi@dominopos.com>
 */
class ExpiredPaymentNotification extends BaseNotification
{
    /**
     * Get the email templates.
     * Can be overriden in each receipt class if needed.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.expired-payment',
        ];
    }

    public function getEmailSubject()
    {
        return trans('email-expired-payment.subject_digital_product', [], '', 'id');
    }

    /**
     * Get email data with pulsa phone number.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        return array_merge(parent::getEmailData(), [
            'emailSubject' => $this->getEmailSubject(),
        ]);
    }
}
