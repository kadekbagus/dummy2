<?php

namespace Orbit\Notifications\DigitalProduct\WaterBill;

use Orbit\Notifications\DigitalProduct\AbortedPaymentNotification as BaseNotification;

/**
 * Email notification for Aborted Payment (Digital Product).
 *
 * @author Budi <budi@dominopos.com>
 */
class AbortedPaymentNotification extends BaseNotification
{
    protected $signature = 'water-bill-aborted-transaction';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.water-bill.aborted-payment',
            'text' => 'emails.digital-product.water-bill.aborted-payment-text',
        ];
    }
}
