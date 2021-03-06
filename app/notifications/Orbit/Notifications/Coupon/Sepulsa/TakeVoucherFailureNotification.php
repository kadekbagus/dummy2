<?php namespace Orbit\Notifications\Coupon\Sepulsa;

use Orbit\Helper\Notifications\Notification;
use Orbit\Helper\Util\JobBurier;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Carbon\Carbon;

use Orbit\Helper\Sepulsa\API\Responses\TakeVoucherResponse;

use Mail;
use Config;
use Log;
use Queue;
use Exception;

/**
 * Notify developer if TakeVoucher request is failed (first time failure only).
 *
 * @author Budi <budi@dominopos.com>
 */
class TakeVoucherFailureNotification extends Notification
{
    protected $payment = null;

    protected $takeVoucherResponse = '';

    protected $retries = 0;

    protected $contact = null;

    function __construct($payment = null, TakeVoucherResponse $takeVoucherResponse, $retries = 0)
    {
        $this->payment              = $payment;
        $this->takeVoucherResponse  = $takeVoucherResponse;
        $this->retries              = $retries;
        $this->queueName            = Config::get('orbit.registration.mobile.queue_name');
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
            'sepulsaResponse'   => $this->takeVoucherResponse->getMessage(),
            'retries'           => $this->retries,
            'couponId'          => $this->payment->object_id,
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

            Log::info('Retry #' . $data['retries']);

            $emailTemplate = 'emails.coupon.sepulsa-take-voucher-failed';
            if ($data['retries'] > 0) {
                $emailTemplate = 'emails.coupon.sepulsa-take-voucher-max-retry-reached';
                $data['maxRetry'] = Config::get('orbit.partners_api.sepulsa.take_voucher_max_retry', $data['retries']);
            }

            Mail::send($emailTemplate, $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = 'Take Voucher Failure Notification';

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });

        } catch (Exception $e) {
            Log::info('TakeVoucher email data: ' . serialize($data));
            Log::info('TakeVoucher Failure Notification email exception. Line:' . $e->getLine() . ', Message: ' . $e->getMessage());
        }

        $job->delete();

        // Bury the job for later inspection
        // JobBurier::create($job, function($theJob) {
        //     // The queue driver does not support bury.
        //     $theJob->delete();
        // })->bury();
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
            "Orbit\Notifications\Coupon\Sepulsa\TakeVoucherFailureNotification@toEmail", 
            $this->getEmailData(),
            $this->queueName
        );

        // Other notification method can be added here...
    }
}
