<?php

namespace Orbit\Notifications\DigitalProduct;

use Exception;
use Mail;
use Orbit\Helper\Notifications\Contracts\EmailNotificationInterface;
use Orbit\Notifications\Payment\PaymentNotification;
use Orbit\Notifications\Traits\CommonHelper;
use Orbit\Notifications\Traits\HasBillTrait;

/**
 * Base Bill purchases notification.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BillNotification extends PaymentNotification implements
    EmailNotificationInterface
{
    use HasBillTrait;

    protected $signature = 'bill-purchases-notification';

    public function __construct($payment = null)
    {
        $this->payment = $payment;
    }

    public function getEmailTemplates()
    {
        return [
            'html' => '',
            'text' => '',
        ];
    }

    public function getEmailSubject()
    {
        return trans('email-bill.subject', [], '', 'id');
    }

    public function getEmailData()
    {
        return [
            'transaction_id' => $this->payment->payment_transaction_id,
        ];
    }

    protected function prepareEmailData($data = [])
    {
        $this->payment = $this->getPayment($data['transaction_id']);

        return [
            'recipientEmail'    => $this->getRecipientEmail(),
            'customerEmail'     => $this->getCustomerEmail(),
            'customerName'      => $this->getCustomerName(),
            'customerPhone'     => $this->getCustomerPhone(),
            'transaction'       => $this->getTransactionData(),
            'billInfo'          => $this->getBillInformation(),
            'cs'                => $this->getContactData(),
            'transactionDateTime' => $this->getTransactionDateTime(),
            'emailSubject'      => $this->getEmailSubject(),
            'supportedLangs'    => $this->getSupportedLanguages(),
        ];
    }

    protected function shouldSendEmail()
    {
        return true;
    }

    public function toEmail($job, $data)
    {
        try {
            $emailData = $this->prepareEmailData($data);

            if ($this->shouldSendEmail()) {
                Mail::send($this->getEmailTemplates(), $emailData, function($mail) use ($emailData) {
                    $emailConfig = Config::get('orbit.registration.mobile.sender');

                    $mail->subject($emailData['emailSubject']);
                    $mail->from($emailConfig['email'], $emailConfig['name']);
                    $mail->to($emailData['recipientEmail']);
                });
            }

        } catch (Exception $e) {
            $this->log(sprintf(
                'Bill email exception. %s(%s): %s',
                $e->getFile(),
                $e->getLine(),
                $e->getMessage()
            ));
        }

        $job->delete();
    }
}
