<?php

namespace Orbit\Notifications\DigitalProduct\InternetProviderBill;

use Orbit\Notifications\DigitalProduct\CanceledPaymentNotification as BaseNotification;

/**
 * Email notification for Canceled Payment (Digital Product).
 *
 * @author Budi <budi@dominopos.com>
 */
class CancelledPaymentNotification extends BaseNotification
{
    protected $signature = 'internet-provider-bill-cancelled-transaction';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.internet-provider-bill.cancelled-payment',
            'text' => 'emails.digital-product.internet-provider-bill.cancelled-payment-text',
        ];
    }
}
