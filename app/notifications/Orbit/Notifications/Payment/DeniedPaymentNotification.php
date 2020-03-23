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
 * Notification for denied payment.
 * Denied means the payment was rejected by payment gateway/provider or
 * canceled by customer (after paying).
 *
 * @author Budi <budi@dominopos.com>
 *
 */
class DeniedPaymentNotification extends AdminNotification
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
            'html' => 'emails.payment.denied',
        ];
    }

    public function toEmail($job, $data)
    {
        try {
            Mail::send($this->getEmailTemplates(), $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = 'Payment was Denied';

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });

        } catch (Exception $e) {
            Log::info('DeniedPayment: exception @ ' . $e->getFile() . ':' . $e->getLine() . ' >> ' . $e->getMessage());
            Log::info('DeniedPayment: email data: ' . serialize($data));
        }

        $job->delete();
    }
}
