<?php

namespace Orbit\Notifications\DigitalProduct\BpjsBill;

use Orbit\Notifications\DigitalProduct\CanceledPaymentNotification as BaseNotification;

/**
 * Email notification for Canceled Payment (Digital Product).
 *
 * @author Budi <budi@dominopos.com>
 */
class CancelledPaymentNotification extends BaseNotification
{
    protected $signature = 'bpjs-bill-cancelled-transaction';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.bpjs-bill.cancelled-payment',
            'text' => 'emails.digital-product.bpjs-bill.cancelled-payment-text',
        ];
    }
}
