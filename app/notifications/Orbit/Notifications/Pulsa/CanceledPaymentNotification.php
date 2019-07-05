<?php namespace Orbit\Notifications\Pulsa;

use DB;
use Mail;
use Config;
use Log;
use Queue;
use Exception;

use Orbit\Notifications\Payment\CanceledPaymentNotification as Base;

/**
 * Email notification for Canceled Payment (Pulsa).
 *
 * @author Budi <budi@dominopos.com>
 */
class CanceledPaymentNotification extends Base
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
            'html' => 'emails.canceled-payment-pulsa',
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
            'emailSubject'      => trans('email-canceled-payment.subject_pulsa', [], '', 'id'),
        ]);
    }
}
