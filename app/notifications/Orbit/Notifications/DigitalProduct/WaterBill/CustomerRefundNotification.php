<?php

namespace Orbit\Notifications\DigitalProduct\WaterBill;

use Orbit\Notifications\DigitalProduct\CustomerRefundNotification as BaseNotification;

/**
 * Notify Customers that we refunded the their payment.
 *
 * @author Budi <budi@dominopos.com>
 */
class CustomerRefundNotification extends BaseNotification
{
    protected $signature = 'water-bill-customer-refund-transaction';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.water-bill.refunded-payment',
            'text' => 'emails.digital-product.water-bill.refunded-payment-text',
        ];
    }
}
