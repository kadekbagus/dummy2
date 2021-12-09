<?php

namespace Orbit\Notifications\DigitalProduct\WaterBill;

use Orbit\Notifications\DigitalProduct\ExpiredPaymentNotification as BaseNotification;

/**
 * Email notification for Expired Payment (Digital Product).
 *
 * @author Budi <budi@dominopos.com>
 */
class ExpiredPaymentNotification extends BaseNotification
{
    protected $signature = 'water-bill-expired-transaction';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.water-bill.expired-payment',
            'text' => 'emails.digital-product.water-bill.expired-payment-text',
        ];
    }
}
