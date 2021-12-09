<?php

namespace Orbit\Notifications\DigitalProduct\InternetProviderBill;

use Orbit\Notifications\DigitalProduct\ExpiredPaymentNotification as BaseNotification;

/**
 * Email notification for Expired Payment (Digital Product).
 *
 * @author Budi <budi@dominopos.com>
 */
class ExpiredPaymentNotification extends BaseNotification
{
    protected $signature = 'internet-provider-bill-expired-transaction';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.internet-provider-bill.expired-payment',
            'text' => 'emails.digital-product.internet-provider-bill.expired-payment-text',
        ];
    }
}
