<?php

namespace Orbit\Notifications\DigitalProduct\InternetProviderBill;

use Orbit\Notifications\DigitalProduct\BillNotification;
use PaymentTransaction;

/**
 * Pending Payment Notification for Digital Product.
 *
 * @author Budi <budi@dominopos.com>
 */
class PendingPaymentNotification extends BillNotification
{
    /**
     * Signature/ID for this notification.
     * @var string
     */
    protected $signature = 'internet-provider-bill-pending-transaction';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.internet-provider-bill.pending-payment',
            'text' => 'emails.digital-product.internet-provider-bill.pending-payment-text',
        ];
    }

    public function getEmailSubject()
    {
        return trans('email-pending-payment.subject', [], '', 'id');
    }

    protected function shouldSendEmail()
    {
        return $this->payment->status === PaymentTransaction::STATUS_PENDING;
    }

    protected function prepareEmailData($data = [])
    {
        return array_merge(parent::prepareEmailData($data), [
            'cancelUrl' => $this->getCancelUrl() . '&type=internet-provider-bill',
            'myWalletUrl' => $this->getMyPurchasesUrl('/bills'),
        ]);
    }
}
