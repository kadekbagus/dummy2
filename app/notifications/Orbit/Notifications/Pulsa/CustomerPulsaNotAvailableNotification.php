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
        $template = $this->objectType === 'pulsa'
            ? 'emails.pulsa.customer-pulsa-not-available'
            : 'emails.data-plan.customer-data-plan-not-available';

        return ['html' => $template];
    }

    /**
     * Get email subject.
     *
     * @return [type] [description]
     */
    protected function getEmailSubject()
    {
        return $this->objectType === 'pulsa'
            ? trans('email-coupon-not-available.subject_pulsa', [], '', 'id')
            : trans('email-coupon-not-available.subject_data_plan', [], '', 'id');
    }

    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        $this->getObjectType();

        return [
            'recipientEmail'    => $this->getRecipientEmail(),
            'customerEmail'     => $this->getCustomerEmail(),
            'customerName'      => $this->getCustomerName(),
            'customerPhone'     => $this->getCustomerPhone(),
            'transaction'       => $this->getTransactionData(),
            'cs'                => $this->getContactData(),
            'transactionDateTime' => $this->payment->getTransactionDate('d F Y, H:i ') . " {$this->getLocalTimezoneName($this->payment->timezone_name)}",
            'subject'           => $this->getEmailSubject(),
            'template'          => $this->getEmailTemplates(),
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
            Mail::send($data['template'], $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = $data['subject'];

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
