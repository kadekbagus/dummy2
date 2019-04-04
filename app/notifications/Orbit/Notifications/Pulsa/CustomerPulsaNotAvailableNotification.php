<?php namespace Orbit\Notifications\Pulsa;

use Exception;
use Log;
use Mail;
use Config;
use Orbit\Helper\Notifications\Contracts\EmailNotificationInterface;
use Orbit\Helper\Notifications\CustomerNotification;
use Orbit\Notifications\Traits\HasContactTrait;
use Orbit\Notifications\Traits\HasPaymentTrait;

/**
 * Notify Customer that we can not process the pulsa.
 *
 * @author Budi <budi@dominopos.com>
 */
class CustomerPulsaNotAvailableNotification extends CustomerNotification implements EmailNotificationInterface
{
    use HasPaymentTrait, HasContactTrait;

    /**
     * Indicate if we should push this job to queue.
     * @var boolean
     */
    protected $shouldQueue = true;

    /**
     * @var integer
     */
    protected $notificationDelay = 3;

    function __construct($payment = null)
    {
        $this->payment = $payment;
    }

    public function getRecipientEmail()
    {
        return $this->getCustomerEmail();
    }

    /**
     * Get the email templates that will be used.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.pulsa.customer-pulsa-not-available',
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
            'cs'                => $this->getContactData(),
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

                $subject = trans('email-coupon-not-available.subject_pulsa');

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });

        } catch (Exception $e) {
            Log::info('PulsaNotAvailable: email exception. File: ' . $e->getFile() . ', Lines:' . $e->getLine() . ', Message: ' . $e->getMessage());
            Log::info('PulsaNotAvailable: email data: ' . serialize($data));
        }

        $job->delete();
    }
}
