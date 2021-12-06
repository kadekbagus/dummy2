<?php

namespace Orbit\Notifications\DigitalProduct\PBBTaxBill;

use Orbit\Notifications\DigitalProduct\ExpiredPaymentNotification as BaseNotification;

/**
 * Email notification for Expired Payment (Digital Product).
 *
 * @author Budi <budi@dominopos.com>
 */
class ExpiredPaymentNotification extends BaseNotification
{
    protected $signature = 'pbb-tax-bill-expired-transaction';

    public function getEmailTemplates()
    {
        return array_merge(parent::getEmailTemplates(), [
            'text' => 'emails.digital-product.pbb-tax-bill.expired-payment-text',
        ]);
    }
}
