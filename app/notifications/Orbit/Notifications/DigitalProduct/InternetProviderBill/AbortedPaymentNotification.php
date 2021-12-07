<?php

namespace Orbit\Notifications\DigitalProduct\InternetProviderBill;

use Orbit\Notifications\DigitalProduct\AbortedPaymentNotification as BaseNotification;

/**
 * Email notification for Aborted Payment (Digital Product).
 *
 * @author Budi <budi@dominopos.com>
 */
class AbortedPaymentNotification extends BaseNotification
{
    protected $signature = 'internet-provider-bill-aborted-transaction';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.internet-provider-bill.aborted-payment',
            'text' => 'emails.digital-product.internet-provider-bill.aborted-payment-text',
        ];
    }
}
