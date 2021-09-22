<?php

namespace Orbit\Notifications\Order;

use Exception;
use Mail;
use Orbit\Notifications\Pulsa\CustomerRefundNotification as BaseNotification;
use Orbit\Notifications\Traits\CommonHelper;
use Orbit\Notifications\Traits\HasOrderTrait;

/**
 * Notify Customers that we refunded the their payment.
 *
 * @author Budi <budi@dominopos.com>
 */
class CustomerRefundNotification extends BaseNotification
{
    use HasOrderTrait, CommonHelper;

    protected $logID = 'OrderRefundNotification';

    /**
     * Get the email templates that will be used.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.order.customer-payment-refunded',
            // 'text' => 'emails.order.customer-payment-refunded-text'
        ];
    }

    public function getEmailSubject()
    {
        return trans('email-customer-refund.order.subject', [], '', 'id');
    }

    public function getEmailData()
    {
        return [
            'transactionId' => $this->payment->payment_transaction_id,
            'reason' => $this->reason,
        ];
    }

    private function prepareMailData($data)
    {
        return [
            'recipientEmail'    => $this->getRecipientEmail(),
            'customerEmail'     => $this->getCustomerEmail(),
            'customerName'      => $this->getCustomerName(),
            'customerPhone'     => $this->getCustomerPhone(),
            'subject'           => $this->getEmailSubject(),
            'sender'            => $this->getSenderInfo(),
            'transaction'       => $this->getTransactionData(),
            'transactionDateTime' => $this->getTransactionDateTime(),
            'cs'                => $this->getContactData(),
            'supportedLangs'    => $this->getSupportedLanguages(),
        ];
    }

    public function toEmail($job, $data)
    {
        try {
            $this->payment = $this->getPayment($data['transactionId']);

            $data = array_merge($data, $this->prepareMailData($data));

            Mail::send($this->getEmailTemplates(), $data, function($mail) use ($data) {
                $mail->subject($data['subject']);
                $mail->from($data['sender']['email'], $data['sender']['name']);
                $mail->to($data['recipientEmail']);
                $mail->cc($data['cs']['email'], $data['cs']['name']);
            });

        } catch (Exception $e) {
            $this->log(sprintf(
                'exception: %s(%s): %s',
                $e->getFile(),
                $e->getLine(),
                $e->getMessage()
            ));
        }
    }
}
