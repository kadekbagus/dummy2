<?php

namespace Orbit\Notifications\DigitalProduct\ElectricityBill;

use Orbit\Notifications\DigitalProduct\AbortedPaymentNotification as BaseNotification;

/**
 * Email notification for Aborted Payment (Digital Product).
 *
 * @author Budi <budi@dominopos.com>
 */
class AbortedPaymentNotification extends BaseNotification
{
    protected $signature = 'electricity-bill-aborted-transaction';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.electricity-bill.aborted-payment',
            'text' => 'emails.digital-product.electricity-bill.aborted-payment-text',
        ];
    }
}
