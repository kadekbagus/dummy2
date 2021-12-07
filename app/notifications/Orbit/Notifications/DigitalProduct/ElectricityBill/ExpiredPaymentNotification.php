<?php

namespace Orbit\Notifications\DigitalProduct\ElectricityBill;

use Orbit\Notifications\DigitalProduct\ExpiredPaymentNotification as BaseNotification;

/**
 * Email notification for Expired Payment (Digital Product).
 *
 * @author Budi <budi@dominopos.com>
 */
class ExpiredPaymentNotification extends BaseNotification
{
    protected $signature = 'electricity-bill-expired-transaction';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.electricity-bill.expired-payment',
            'text' => 'emails.digital-product.electricity-bill.expired-payment-text',
        ];
    }
}
