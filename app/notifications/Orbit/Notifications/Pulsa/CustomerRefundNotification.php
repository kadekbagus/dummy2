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
 * Notify Customers that we refunded the their payment.
 *
 * @author Budi <budi@dominopos.com>
 */
class CustomerRefundNotification extends CustomerNotification implements EmailNotificationInterface
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

    private $reason = '';

    protected $context = 'transaction';

    protected $signature = 'refund-notification';

    function __construct($payment = null, $reason = '')
    {
        $this->payment = $payment;
        $this->reason = $reason;
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
            'html' => 'emails.pulsa.customer-payment-refunded',
        ];
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
            'sender'            => $this->getSenderInfo(),
            'subject'           => trans('email-customer-refund.subject_pulsa', [], '', 'id'),
            'reason'            => $this->reason,
            'pulsaPhone'        => $this->getPulsaPhone(),
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
                $mail->subject($data['subject']);
                $mail->from($data['sender']['email'], $data['sender']['name']);
                $mail->to($data['recipientEmail']);
                $mail->cc($data['cs']['email'], $data['cs']['name']);
            });

        } catch (Exception $e) {
            Log::info($this->signature . ': email exception. File: ' . $e->getFile() . ', Lines:' . $e->getLine() . ', Message: ' . $e->getMessage());
            Log::info($this->signature . ': email data: ' . serialize($data));
        }

        $job->delete();
    }

    private function getPulsaPhone()
    {
        return $this->payment->extra_data;
    }
}
