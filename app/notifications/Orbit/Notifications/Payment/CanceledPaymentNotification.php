<?php namespace Orbit\Notifications\Payment;

use DB;
use Mail;
use Config;
use Log;
use Queue;
use Exception;

use Orbit\Helper\Notifications\CustomerNotification;
use Orbit\Helper\Notifications\Contracts\EmailNotificationInterface;

use Orbit\Notifications\Traits\HasPaymentTrait;
use Orbit\Notifications\Traits\HasContactTrait;

/**
 * Email notification for Canceled Payment.
 *
 * @author Budi <budi@dominopos.com>
 */
class CanceledPaymentNotification extends CustomerNotification implements EmailNotificationInterface
{
    use HasPaymentTrait, HasContactTrait;

    protected $shouldQueue = true;

    /**
     * Signature/ID of this notification.
     * @var string
     */
    protected $signature = 'canceled-transaction';

    function __construct($payment = null)
    {
        $this->payment = $payment;
    }

    public function getRecipientEmail()
    {
        return $this->getCustomerEmail();
    }

    /**
     * Get the email templates.
     * At the moment we can use same template for both Sepulsa and Hot Deals.
     * Can be overriden in each receipt class if needed.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.payment.canceled-payment',
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
            'transactionDateTime' => $this->payment->getTransactionDate('d F Y, H:i ') . " {$this->getLocalTimezoneName($this->payment->timezone_name)}",
            'buyUrl'            => $this->getBuyUrl(),
            'emailSubject'      => trans('email-canceled-payment.subject', [], '', 'id'),
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
                $emailConfig = Config::get('orbit.contact_information.customer_service');

                $subject = $data['emailSubject'];

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
                $mail->replyTo($emailConfig['email'], $emailConfig['name']);
            });
        } catch (Exception $e) {
            Log::debug('Notification: CanceledPayment email exception. Line:' . $e->getLine() . ', Message: ' . $e->getMessage());
        }

        $job->delete();
    }
}
