<?php

namespace Orbit\Notifications\Order;

use Config;
use Discount;
use Exception;
use Mail;
use Orbit\Helper\Resource\ImageTransformer;
use Orbit\Helper\Resource\MediaQuery;
use Orbit\Notifications\Payment\ReceiptNotification as BaseReceiptNotification;
use Orbit\Notifications\Traits\HasOrderTrait;
use Orbit\Notifications\Traits\HasPaymentTrait;
use PaymentTransaction;

/**
 * Receipt Notification for Customer after purchasing Pulsa.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReceiptNotification extends BaseReceiptNotification
{
    use HasOrderTrait;

    protected $signature = 'brand-product-order-receipt-notification';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.order.receipt',
        ];
    }

    /**
     * Only send email notification at the moment.
     *
     * @override
     * @return [type] [description]
     */
    protected function notificationMethods()
    {
        // Set to notify via email
        return ['email'];
    }

    public function getEmailData()
    {
        return [
            'transactionId' => $this->payment->payment_transaction_id
        ];
    }

    private function prepareMailData($data)
    {
        $this->payment = $this->getPayment($data['transactionId']);

        return [
            'recipientEmail'    => $this->getRecipientEmail(),
            'customerEmail'     => $this->getCustomerEmail(),
            'customerName'      => $this->getCustomerName(),
            'customerPhone'     => $this->getCustomerPhone(),
            'transaction'       => $this->getTransactionData(),
            'cs'                => $this->getContactData(),
            'myWalletUrl'       => $this->getMyPurchasesUrl('/orders'),
            'transactionDateTime' => $this->getTransactionDateTime(),
            'emailSubject'      => $this->getEmailSubject(),
        ];
    }

    public function toEmail($job, $data)
    {
        try {
            $mailData = $this->prepareMailData($data);

            Mail::send($this->getEmailTemplates(), $mailData, function($mail) use ($mailData) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = $mailData['emailSubject'];

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($mailData['recipientEmail']);
            });

        } catch (Exception $e) {
            \Log::info(serialize($e));
        }

        $job->delete();
    }
}
