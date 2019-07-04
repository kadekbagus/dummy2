<?php namespace Orbit\Notifications\Pulsa;

use Orbit\Notifications\Payment\AbortedPaymentNotification as Base;

/**
 * Email notification for Aborted Payment (Pulsa).
 *
 * @author Budi <budi@dominopos.com>
 */
class AbortedPaymentNotification extends Base
{
    function __construct($payment = null)
    {
        parent::__construct($payment);
    }

    /**
     * Get the email templates.
     * Can be overriden in each receipt class if needed.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.aborted-payment-pulsa',
        ];
    }

    /**
     * Get email data with pulsa phone number.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        return array_merge(parent::getEmailData(), [
            'pulsaPhoneNumber' => $this->payment->extra_data,
            'emailSubject' => trans('email-aborted-payment.subject_pulsa', [], '', 'id'),
        ]);
    }
}
