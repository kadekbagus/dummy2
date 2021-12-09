<?php

namespace Orbit\Notifications\DigitalProduct\PBBTaxBill;

use Orbit\Notifications\DigitalProduct\AbortedPaymentNotification as BaseNotification;

/**
 * Email notification for Aborted Payment (Digital Product).
 *
 * @author Budi <budi@dominopos.com>
 */
class AbortedPaymentNotification extends BaseNotification
{
    protected $signature = 'pbb-tax-bill-aborted-transaction';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.pbb-tax-bill.aborted-payment',
            'text' => 'emails.digital-product.pbb-tax-bill.aborted-payment-text',
        ];
    }
}
