<?php

namespace Orbit\Notifications\DigitalProduct;

use Config;
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
abstract class BillNotification extends PaymentNotification implements
    EmailNotificationInterface
{
    use HasBillTrait;

    protected $signature = 'bill-purchase-notification';

    public function __construct($payment = null)
    {
        $this->payment = $payment;
    }

    abstract public function getEmailTemplates();

    abstract public function getEmailSubject();

    public function getRecipientEmail()
    {
        return $this->payment->user_email;
    }

    public function getEmailData()
    {
        return [
            'transaction_id' => $this->payment->payment_transaction_id,
        ];
    }

    protected function getProductName()
    {
        return $this->payment->details->filter(function($detail) {
            return $detail->object_type === 'digital_product';
        })->first()->object_name;
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
            'productName'       => $this->getProductName(),
            'paymentMethod'     => $this->getPaymentMethod(),
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
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                Mail::send(
                    $this->getEmailTemplates(),
                    $emailData,
                    function($mail) use ($emailData, $emailConfig) {
                        $mail->subject($emailData['emailSubject']);
                        $mail->from($emailConfig['email'], $emailConfig['name']);
                        $mail->to($emailData['recipientEmail']);
                    }
                );
            }
            else {
                $this->log('Not sending any notification due to shouldSendEmail is false.');
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
