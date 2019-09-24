<?php namespace Orbit\Notifications\Payment;

use Config;
use Exception;
use Log;
use Mail;
use Orbit\Helper\Notifications\AdminNotification;
use Orbit\Helper\Notifications\Notification;
use Orbit\Helper\Util\JobBurier;
use Orbit\Notifications\Traits\HasPaymentTrait;
use Queue;

/**
 * Notification for supsicious payment.
 *
 * @author Budi <budi@dominopos.com>
 *
 */
class SuspiciousPaymentNotification extends AdminNotification
{
    use HasPaymentTrait;

    protected $shouldQueue = true;

    function __construct($payment = null)
    {
        $this->payment = $payment;
    }

    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        return [
            'recipientEmail'    => $this->getRecipientEmail(),
            'paymentId'         => $this->payment->payment_transaction_id,
            'externalPaymentId' => $this->payment->external_payment_transaction_id,
            'paymentMethod'     => $this->payment->payment_method,
            'couponId'          => $this->payment->details->first()->object_id,
        ];
    }

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.payment.suspicious',
        ];
    }

    public function toEmail($job, $data)
    {
        try {
            $blacklistedEmails = [
                'sputraqu@yahoo.com'
            ];

            if (in_array($data['recipientEmail'], $blacklistedEmails)) {
                $job->delete();
                return;
            }

            Mail::send($this->getEmailTemplates(), $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = 'Payment Need Validation (Suspicious)';

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });

        } catch (Exception $e) {
            Log::info('SuspiciousPayment: exception @ ' . $e->getFile() . ':' . $e->getLine() . ' >> ' . $e->getMessage());
            Log::info('SuspiciousPayment: email data: ' . serialize($data));
        }

        $job->delete();
    }
}
