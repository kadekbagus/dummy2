<?php namespace Orbit\Notifications\Pulsa;

use Orbit\Helper\Notifications\AdminNotification;
use Orbit\Helper\Util\JobBurier;
use Carbon\Carbon;

use Orbit\Notifications\Traits\HasPaymentTrait as HasPayment;

use Mail;
use Config;
use Log;
use Queue;
use Exception;

/**
 * Notify Admin that the coupon we try to issue is not available.
 *
 * @author Budi <budi@dominopos.com>
 */
class PulsaNotAvailableNotification extends AdminNotification
{
    use HasPayment;

    protected $reason = null;

    /**
     * Indicate if we should push this job to queue.
     * @var boolean
     */
    protected $shouldQueue = true;

    /**
     * @var integer
     */
    protected $notificationDelay = 3;

    function __construct($payment = null, $reason = 'Internal Server Error.')
    {
        $this->payment              = $payment;
        $this->reason               = $reason;
        $this->queueName            = Config::get('orbit.registration.mobile.queue_name');
    }

    /**
     * Get the email templates.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.pulsa.admin-pulsa-not-available',
        ];
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
            'customerEmail'     => $this->getCustomerEmail(),
            'customerName'      => $this->getCustomerName(),
            'customerPhone'     => $this->getCustomerPhone(),
            'transaction'       => $this->getTransactionData(),
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
            Mail::send($this->getEmailTemplates(), $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = '[Admin] Can not Purchase Pulsa';

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });

        } catch (Exception $e) {
            Log::info('PulsaNotAvailable: email exception. Line:' . $e->getLine() . ', Message: ' . $e->getMessage());
            Log::info('PulsaNotAvailable: email data: ' . serialize($data));
        }

        $job->delete();
    }

}
