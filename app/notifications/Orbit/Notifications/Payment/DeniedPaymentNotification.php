<?php namespace Orbit\Notifications\Payment;

use Orbit\Helper\Notifications\Notification;
use Orbit\Helper\Util\JobBurier;

use Mail;
use Config;
use Log;
use Queue;
use Exception;

/**
 * Notification for denied payment.
 * Denied means the payment was rejected by payment gateway/provider or 
 * canceled by customer (after paying).
 *
 * @author Budi <budi@dominopos.com>
 * 
 */
class DeniedPaymentNotification extends Notification
{
    protected $payment = null;

    function __construct($payment = null)
    {
        $this->payment      = $payment;
        $this->queueName    = Config::get('orbit.registration.mobile.queue_name');
    }

    /**
     * Get the email data.
     * 
     * @return [type] [description]
     */
    protected function getEmailData()
    {
        return [
            'recipientEmail'    => $this->getEmailAddress(),
            'paymentId'         => $this->payment->payment_transaction_id,
            'externalPaymentId' => $this->payment->external_payment_transaction_id,
            'paymentMethod'     => $this->payment->payment_method,
            'couponId'          => $this->payment->object_id,
        ];
    }

    public function toEmail($job, $data)
    {
        try {

            Log::info('Payment: Sending notification for Denied Payment... PaymentID: ' . $data['paymentId']);

            $emailTemplate = 'emails.payment.denied';

            Mail::send($emailTemplate, $data, function($mail) use ($data) {
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

    /**
     * Send notification.
     * 
     * @return [type] [description]
     */
    public function send($delay = 1)
    {
        Queue::later(
            $delay,
            "Orbit\Notifications\Payment\DeniedPaymentNotification@toEmail", 
            $this->getEmailData(),
            $this->queueName
        );

        // Other notification method can be added here...
    }
}
