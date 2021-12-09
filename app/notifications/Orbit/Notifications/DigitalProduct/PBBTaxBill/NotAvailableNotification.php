<?php

namespace Orbit\Notifications\DigitalProduct\PBBTaxBill;

use Orbit\Notifications\DigitalProduct\CustomerDigitalProductNotAvailableNotification as BaseNotification;

/**
 * Notify Customer that we can not process the pulsa.
 *
 * @author Budi <budi@dominopos.com>
 */
class NotAvailableNotification extends BaseNotification
{
    protected $signature = 'pbb-tax-bill-not-available-product';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.pbb-tax-bill.product-not-available',
            'text' => 'emails.digital-product.pbb-tax-bill.product-not-available-text',
        ];
    }
}
