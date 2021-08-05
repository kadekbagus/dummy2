<?php

namespace Orbit\Notifications\Order\Admin;

use Config;
use Exception;
use Log;
use Mail;
use Orbit\Helper\Notifications\AdminNotification;
use Orbit\Notifications\Traits\CommonHelper;
use Orbit\Notifications\Traits\HasOrderTrait;
use Orbit\Notifications\Traits\HasPaymentTrait as HasPayment;

/**
 * Notify store admin that the new order has been made.
 *
 * @author Budi <budi@dominopos.com>
 */
class NewOrderNotification extends AdminNotification
{
    use CommonHelper,
        HasPayment,
        HasOrderTrait {
            HasOrderTrait::getTransactionData insteadof HasPayment;
        }

    /**
     * Indicate if we should push this job to queue.
     * @var boolean
     */
    protected $shouldQueue = true;

    /**
     * @var integer
     */
    protected $notificationDelay = 3;

    protected $logID = 'ProductOrderNotification';

    protected $signature = 'admin-new-order-notification';

    function __construct($payment = null)
    {
        $this->payment              = $payment;
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
            'html' => 'emails.order.admin.new-order',
        ];
    }

    /**
     * Get the email subject.
     * @return [type] [description]
     */
    public function getEmailSubject()
    {
        return trans('email-order.new-order.subject', [], '', 'en');
    }

    public function getEmailData()
    {
        return [
            'transactionId' => $this->payment->payment_transaction_id,
        ];
    }

    protected function getCustomerName()
    {

    }

    protected function getRecipientEmail()
    {
        return $this->getAdminRecipients();
    }

    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    private function prepareEmailData($data)
    {
        $this->getPayment($data['transactionId']);

        return [
            'recipientEmail'    => '',
            'customerEmail'     => $this->getCustomerEmail(),
            'customerName'      => $this->getCustomerName(),
            'customerPhone'     => $this->getCustomerPhone(),
            'transaction'       => $this->getTransactionData(),
            'transactionDateTime' => $this->payment->getTransactionDate('d F Y, H:i ') . " {$this->getLocalTimezoneName($this->payment->timezone_name)}",
            'emailSubject'      => $this->getEmailSubject(),
            'supportedLangs'    => $this->getSupportedLanguages(),
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
            $emailData = $this->prepareMailData($data);

            foreach($this->getRecipientEmail() as $storeAdmin) {
                $emailData['recipientEmail'] = $storeAdmin->user_email;
                Mail::send($this->getEmailTemplates(), $data, function($mail) use ($emailData) {
                    $emailConfig = Config::get('orbit.registration.mobile.sender');

                    $mail->subject($emailData['emailSubject']);
                    $mail->from($emailConfig['email'], $emailConfig['name']);
                    $mail->to($emailData['recipientEmail']);
                });
            }

        } catch (Exception $e) {
            $this->log('Exception on Line:' . $e->getLine() . ', Message: ' . $e->getMessage());
            $this->log('Exception data: ' . serialize($data));
        }

        $job->delete();
    }

}
