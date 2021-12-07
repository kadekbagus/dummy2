<?php

namespace Orbit\Notifications\DigitalProduct\ElectricityBill;

use Orbit\Notifications\DigitalProduct\CustomerDigitalProductNotAvailableNotification as BaseNotification;

/**
 * Notify Customer that we can not process the pulsa.
 *
 * @author Budi <budi@dominopos.com>
 */
class NotAvailableNotification extends BaseNotification
{
    protected $signature = 'electricity-bill-not-available-product';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.electricity-bill.product-not-available',
            'text' => 'emails.digital-product.electricity-bill.product-not-available-text',
        ];
    }
}
