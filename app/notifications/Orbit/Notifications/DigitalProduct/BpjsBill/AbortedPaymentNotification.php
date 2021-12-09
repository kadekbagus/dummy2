<?php

namespace Orbit\Notifications\DigitalProduct\BpjsBill;

use Orbit\Notifications\DigitalProduct\AbortedPaymentNotification as BaseNotification;

/**
 * Email notification for Aborted Payment (Digital Product).
 *
 * @author Budi <budi@dominopos.com>
 */
class AbortedPaymentNotification extends BaseNotification
{
    protected $signature = 'bpjs-bill-aborted-transaction';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.bpjs-bill.aborted-payment',
            'text' => 'emails.digital-product.bpjs-bill.aborted-payment-text',
        ];
    }
}
