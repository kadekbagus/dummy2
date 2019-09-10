<?php namespace Orbit\Notifications\Pulsa;

use DB;
use Mail;
use Config;
use Log;
use Queue;
use Exception;

use Orbit\Notifications\Payment\ExpiredPaymentNotification as Base;

/**
 * Email notification for Expired Payment (Pulsa).
 *
 * @author Budi <budi@dominopos.com>
 */
class ExpiredPaymentNotification extends Base
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
        $template = $this->objectType === 'pulsa'
            ? 'emails.pulsa.expired-payment'
            : 'emails.data-plan.expired-payment';

        return [
            'html' => $template,
        ];
    }

    public function getEmailSubject()
    {
        return $this->objectType === 'pulsa'
            ? trans('email-expired-payment.subject_pulsa', [], '', 'id')
            : trans('email-expired-payment.subject_data_plan', [], '', 'id');
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
            'emailSubject' => trans('email-expired-payment.subject_pulsa', [], '', 'id'),
        ]);
    }
}
