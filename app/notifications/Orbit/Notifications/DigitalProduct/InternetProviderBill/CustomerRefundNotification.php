<?php

namespace Orbit\Notifications\DigitalProduct\InternetProviderBill;

use Orbit\Notifications\DigitalProduct\CustomerRefundNotification as BaseNotification;

/**
 * Notify Customers that we refunded the their payment.
 *
 * @author Budi <budi@dominopos.com>
 */
class CustomerRefundNotification extends BaseNotification
{
    protected $signature = 'internet-provider-bill-customer-refund-transaction';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.internet-provider-bill.refunded-payment',
            'text' => 'emails.digital-product.internet-provider-bill.refunded-payment-text',
        ];
    }
}
