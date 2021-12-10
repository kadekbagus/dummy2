<?php

namespace Orbit\Notifications\DigitalProduct\BpjsBill;

use Orbit\Notifications\DigitalProduct\CustomerRefundNotification as BaseNotification;

/**
 * Notify Customers that we refunded the their payment.
 *
 * @author Budi <budi@dominopos.com>
 */
class CustomerRefundNotification extends BaseNotification
{
    protected $signature = 'bpjs-bill-customer-refund-transaction';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.bpjs-bill.refunded-payment',
            'text' => 'emails.digital-product.bpjs-bill.refunded-payment-text',
        ];
    }
}
