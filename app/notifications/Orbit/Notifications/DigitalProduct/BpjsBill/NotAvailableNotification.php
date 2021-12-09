<?php

namespace Orbit\Notifications\DigitalProduct\BpjsBill;

use Orbit\Notifications\DigitalProduct\CustomerDigitalProductNotAvailableNotification as BaseNotification;

/**
 * Notify Customer that we can not process the pulsa.
 *
 * @author Budi <budi@dominopos.com>
 */
class NotAvailableNotification extends BaseNotification
{
    protected $signature = 'bpjs-bill-not-available-product';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.bpjs-bill.product-not-available',
            'text' => 'emails.digital-product.bpjs-bill.product-not-available-text',
        ];
    }
}
