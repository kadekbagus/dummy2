<?php namespace Orbit\Notifications\Coupon\Sepulsa;

use Orbit\Helper\Notifications\Notification;
use Orbit\Helper\Util\JobBurier;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Carbon\Carbon;

use Orbit\Helper\Sepulsa\API\Response\TakeVoucherResponse;

use Mail;
use Config;
use Log;
use Queue;
use Exception;

/**
 * Notify developer if TakeVoucher request reached maximum attempts allowed.
 *
 * @author Budi <budi@dominopos.com>
 */
class TakeVoucherMaximumRetryReachedNotification extends Notification
{
    protected $payment = null;

    protected $takeVoucherResponse = '';

    protected $contact = null;

    function __construct($payment = null, TakeVoucherResponse $takeVoucherResponse)
    {
        $this->payment              = $payment;
        $this->takeVoucherResponse  = $takeVoucherResponse;
        $this->queueName            = Config::get('orbit.registration.mobile.queue_name');
        $this->contact              = Config::get('orbit.contact_information');
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
            'paymentId'         => $payment->payment_transaction_id,
            'externalPaymentId' => $payment->external_payment_transaction_id,
            'paymentMethod'     => $payment->payment_method,
            'sepulsaResponse'   => $this->takeVoucherResponse->getMessage(),
            'maxRetry'          => Config::get('orbit.partners_api.sepulsa.take_voucher_max_retry', 0);
        ];
    }

    /**
     * Notify via email.
     * This method act as custom Queue handler.
     * 
     * @param  [type] $notifiable [description]
     * @return [type]             [description]
     */
    public function toEmail($job, $data)
    {
        try {

            $emailTemplate = 'emails.coupon.sepulsa-take-voucher-max-retry-reached';

            Mail::send($emailTemplate, $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = 'Take Voucher Failure Notification';

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });

            $job->delete();

            // Bury the job for later inspection
            JobBurier::create($job, function($theJob) {
                // The queue driver does not support bury.
                $theJob->delete();
            })->bury();

        } catch (Exception $e) {
            Log::debug('Take Voucher Failure Notification email exception. Line:' . $e->getLine() . ', Message: ' . $e->getMessage());
        }
    }

    /**
     * Send notification.
     * 
     * @return [type] [description]
     */
    public function send()
    {
        Queue::push(
            'Orbit\\Notifications\\Coupon\\Sepulsa\\TakeVoucherFailureNotification@toEmail', 
            $this->getEmailData(),
            $this->queueName
        );

        // Other notification method can be added here...
    }
}
