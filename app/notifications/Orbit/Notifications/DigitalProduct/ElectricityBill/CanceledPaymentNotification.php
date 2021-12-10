<?php

namespace Orbit\Notifications\DigitalProduct\ElectricityBill;

use Orbit\Notifications\DigitalProduct\CanceledPaymentNotification as BaseNotification;

/**
 * Email notification for Canceled Payment (Digital Product).
 *
 * @author Budi <budi@dominopos.com>
 */
class CanceledPaymentNotification extends BaseNotification
{
    protected $signature = 'electricity-bill-cancelled-transaction';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.electricity-bill.cancelled-payment',
            'text' => 'emails.digital-product.electricity-bill.cancelled-payment-text',
        ];
    }
}
