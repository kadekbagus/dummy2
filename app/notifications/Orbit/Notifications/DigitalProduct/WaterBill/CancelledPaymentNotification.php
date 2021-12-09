<?php

namespace Orbit\Notifications\DigitalProduct\WaterBill;

use Orbit\Notifications\DigitalProduct\CanceledPaymentNotification as BaseNotification;

/**
 * Email notification for Canceled Payment (Digital Product).
 *
 * @author Budi <budi@dominopos.com>
 */
class CancelledPaymentNotification extends BaseNotification
{
    protected $signature = 'water-bill-cancelled-transaction';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.water-bill.cancelled-payment',
            'text' => 'emails.digital-product.water-bill.cancelled-payment-text',
        ];
    }
}
