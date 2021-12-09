<?php

namespace Orbit\Notifications\DigitalProduct\PBBTaxBill;

use Orbit\Notifications\DigitalProduct\CanceledPaymentNotification as BaseNotification;

/**
 * Email notification for Canceled Payment (Digital Product).
 *
 * @author Budi <budi@dominopos.com>
 */
class CancelledPaymentNotification extends BaseNotification
{
    protected $signature = 'pbb-tax-bill-cancelled-transaction';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.pbb-tax-bill.cancelled-payment',
            'text' => 'emails.digital-product.pbb-tax-bill.cancelled-payment-text',
        ];
    }
}
